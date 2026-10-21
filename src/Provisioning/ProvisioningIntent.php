<?php

namespace Sabbajohn\PulseLaravel\Provisioning;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ProvisioningIntent extends Model
{
    protected $table = 'pulse_provisioning_intents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['snapshot', 'result', 'idempotency_key', 'lease'];

    protected $casts = [
        'snapshot' => 'encrypted:array', 'result' => 'encrypted:array',
        'available_at' => 'datetime', 'leased_until' => 'datetime',
    ];

    public static function integrationDigest(string $integration): string
    {
        if (! preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $integration)) {
            throw new LogicException('Provisioning integration must be a stable identifier.');
        }

        // Canonical lowercase hex of the exact bytes; never case-fold the name.
        return hash('sha256', $integration);
    }

    public function scopeForIntegration(Builder $query, string $integration): Builder
    {
        $query->useWritePdo()->where('integration_hash', self::integrationDigest($integration));

        // A digest scopes the unique index; a binary comparison also fails
        // closed for corrupt/misassigned digests, independent of DB collation.
        return match ($this->getConnection()->getDriverName()) {
            'sqlite' => $query->whereRaw('CAST(integration AS BLOB) = CAST(? AS BLOB)', [$integration]),
            'pgsql' => $query->whereRaw("convert_to(integration, 'UTF8') = convert_to(?, 'UTF8')", [$integration]),
            'mysql', 'mariadb' => $query->whereRaw('BINARY integration = BINARY ?', [$integration]),
            default => throw new LogicException('Unsupported provisioning database driver.'),
        };
    }

    public function assertIntegration(string $integration): void
    {
        if (! hash_equals($integration, (string) $this->integration)
            || ! hash_equals(self::integrationDigest($integration), (string) $this->integration_hash)) {
            throw new LogicException('Provisioning integration identity mismatch.');
        }
    }
}

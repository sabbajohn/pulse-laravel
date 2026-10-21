<?php

namespace Sabbajohn\PulseLaravel\Provisioning;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Sabbajohn\PulsePhp\Exceptions\AuthenticationException;
use Sabbajohn\PulsePhp\Exceptions\ConflictException;
use Sabbajohn\PulsePhp\Exceptions\NotFoundException;
use Sabbajohn\PulsePhp\Exceptions\PulseException;
use Sabbajohn\PulsePhp\Exceptions\RequestFailedException;
use Sabbajohn\PulsePhp\Exceptions\ValidationException;
use Sabbajohn\PulsePhp\PulseClient;

class PartnerProvisioner
{
    /** Must be called on the same DB connection/transaction as the local customer. */
    public function enqueue(string $externalCustomerId, array $payload): ProvisioningIntent
    {
        $this->assertEnabled();
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/D', $externalCustomerId)) {
            throw new LogicException('A stable external customer identifier is required.');
        }
        if (isset($payload['external_customer_id']) && $payload['external_customer_id'] !== $externalCustomerId) {
            throw new LogicException('External customer identity mismatch.');
        }
        $payload['external_customer_id'] = $externalCustomerId;
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $integration = $this->integration();
        $identity = ['integration_hash' => ProvisioningIntent::integrationDigest($integration),
            'external_id_hash' => hash('sha256', $externalCustomerId)];

        return DB::transaction(function () use ($payload, $hash, $identity, $integration): ProvisioningIntent {
            if (DB::connection()->getDriverName() === 'sqlite') {
                // SQLite has no row locks: reserve its writer before the read.
                ProvisioningIntent::query()->forIntegration($integration)->where($identity)
                    ->update(['updated_at' => DB::raw('updated_at')]);
            }
            $intent = ProvisioningIntent::query()->forIntegration($integration)->where($identity)->lockForUpdate()->first();
            if (! $intent) {
                try {
                    // Nested transaction provides a savepoint on PostgreSQL
                    // when a concurrent creator wins the unique constraint.
                    $intent = DB::transaction(fn () => ProvisioningIntent::query()->create(array_merge($identity, [
                        'integration' => $integration, 'id' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(),
                        'payload_hash' => $hash, 'snapshot' => $payload,
                        'status' => 'pending', 'available_at' => now(),
                    ])));
                } catch (QueryException $exception) {
                    $intent = ProvisioningIntent::query()->forIntegration($integration)->where($identity)->lockForUpdate()->first();
                    if (! $intent) {
                        throw $exception;
                    }
                }
            }
            $intent->assertIntegration($integration);
            if (! hash_equals($intent->payload_hash, $hash)) {
                throw new LogicException('Customer already has another provisioning payload.');
            }
            // Queue only the identifier, never the customer/secret. The scanner
            // recovers a crash or queue outage after commit and before dispatch.
            DB::afterCommit(function () use ($intent): void {
                try {
                    // Force destruction of PendingDispatch inside the try.
                    $dispatch = ProcessProvisioningIntent::dispatch($intent->id);
                    unset($dispatch);
                } catch (\Throwable) {
                    // Customer is already committed. Preserve its successful
                    // transaction and let the durable scanner recover dispatch.
                    logger()->warning('Pulse provisioning dispatch deferred.', ['intent_id' => $intent->id]);
                }
            });

            return $intent;
        }, 5);
    }

    /** Resolve only from an already-authorized local customer context. */
    public function credentialsFor(string $externalCustomerId): array
    {
        $this->assertEnabled();
        $intent = ProvisioningIntent::query()->forIntegration($this->integration())
            ->where('external_id_hash', hash('sha256', $externalCustomerId))->first();
        $intent?->assertIntegration($this->integration());
        $result = $intent?->result;
        if ($intent?->status !== 'completed' || ($result['status'] ?? null) !== 'active') {
            // Never return null: the ordinary factory treats null as permission
            // to fall back to static credentials, possibly for another tenant.
            throw new LogicException('Pulse partner account is not ready for this customer.');
        }

        return ['base_url' => config('pulse.provisioning.base_url') ?: config('pulse.base_url'),
            'api_token' => $result['api_token']];
    }

    public function process(string $id): void
    {
        $this->assertEnabled();
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Provisioning HTTP requires a committed local transaction.');
        }
        $timeout = (int) config('pulse.provisioning.timeout', 15);
        if ($timeout < 1 || $timeout > 60) {
            throw new LogicException('Provisioning timeout must be between 1 and 60 seconds.');
        }
        // A conditional UPDATE is the claim arbiter on SQLite and server DBs.
        $lease = (string) Str::uuid();
        $claimed = ProvisioningIntent::query()->forIntegration($this->integration())->whereKey($id)
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('status', 'pending')->where('available_at', '<=', now());
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'processing')->where('leased_until', '<=', now());
                });
            })->update(['status' => 'processing', 'lease' => $lease, 'leased_until' => now()->addSeconds(120),
                'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $intent = ProvisioningIntent::query()->forIntegration($this->integration())->findOrFail($id);
        $intent->assertIntegration($this->integration());
        try {
            $client = $this->client($timeout);
            $result = $client->provisioning()->createClient($intent->snapshot, $intent->idempotency_key);
            if (! isset($result['api_token'], $result['account_id'], $result['workspace_id'], $result['status'])
                || ! is_string($result['api_token']) || $result['api_token'] === ''
                || ! is_int($result['account_id']) || $result['account_id'] < 1
                || ! is_int($result['workspace_id']) || $result['workspace_id'] < 1
                || ($result['external_customer_id'] ?? null) !== $intent->snapshot['external_customer_id']
                || ! in_array($result['status'], ['active', 'pending_activation'], true)) {
                throw new LogicException('Invalid provisioning response.');
            }
            // The model cast encrypts before the fenced conditional update.
            $intent->result = $result;
            $this->finish($intent, $lease, ['status' => 'completed',
                'result' => $intent->getAttributes()['result'], 'error_code' => null]);
        } catch (AuthenticationException|ConflictException|ValidationException|NotFoundException $exception) {
            $this->finish($intent, $lease, ['status' => 'failed', 'error_code' => 'http_'.$exception->getCode()]);
        } catch (\Throwable $exception) {
            if ($exception instanceof RequestFailedException && $exception->getCode() >= 300
                && $exception->getCode() < 500 && $exception->getCode() !== 429) {
                $this->finish($intent, $lease, ['status' => 'failed', 'error_code' => 'http_'.$exception->getCode()]);

                return;
            }
            // A timeout may have committed remotely: retry the exact key and
            // snapshot. Store a code only; exception text may contain secrets.
            $this->finish($intent, $lease, ['status' => 'pending', 'error_code' => 'retryable',
                'available_at' => now()->addSeconds($this->retryDelay($exception, (int) $intent->attempts))]);
        }
    }

    protected function client(int $timeout): PulseClient
    {
        $url = (string) (config('pulse.provisioning.base_url') ?: config('pulse.base_url'));
        $token = (string) config('pulse.provisioning.partner_token');
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https' || $token === '') {
            throw new LogicException('Partner provisioning requires HTTPS and a server-side partner token.');
        }

        return new PulseClient($url, $token, ['timeout' => $timeout, 'connect_timeout' => min(5, $timeout),
            'allow_redirects' => false]);
    }

    private function finish(ProvisioningIntent $intent, string $lease, array $values): void
    {
        $intent->assertIntegration($this->integration());
        ProvisioningIntent::query()->forIntegration($this->integration())->whereKey($intent->id)->where('lease', $lease)
            ->update(array_merge($values, ['lease' => null, 'leased_until' => null, 'updated_at' => now()]));
    }

    private function integration(): string
    {
        $integration = (string) config('pulse.provisioning.integration', 'default');
        ProvisioningIntent::integrationDigest($integration);

        return $integration;
    }

    private function retryDelay(\Throwable $exception, int $attempts): int
    {
        $delay = min(3600, 30 * (2 ** min(7, $attempts - 1)));
        $retryAfter = $exception instanceof PulseException ? $exception->retryAfter() : null;
        if ($retryAfter !== null) {
            $seconds = ctype_digit($retryAfter) ? (int) $retryAfter
                : (($timestamp = strtotime($retryAfter)) !== false ? $timestamp - now()->timestamp : 0);
            $delay = max($delay, $seconds);
        }

        return $delay;
    }

    private function assertEnabled(): void
    {
        if (! config('pulse.provisioning.enabled', false)) {
            throw new LogicException('Partner provisioning is disabled.');
        }
    }
}

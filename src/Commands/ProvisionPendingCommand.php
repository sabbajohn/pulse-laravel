<?php

namespace Sabbajohn\PulseLaravel\Commands;

use Illuminate\Console\Command;
use Sabbajohn\PulseLaravel\Provisioning\ProcessProvisioningIntent;
use Sabbajohn\PulseLaravel\Provisioning\ProvisioningIntent;

class ProvisionPendingCommand extends Command
{
    protected $signature = 'pulse:provision-pending';

    protected $description = 'Dispatch durable pending and abandoned partner provisioning intents';

    public function handle(): int
    {
        if (! config('pulse.provisioning.enabled', false)) {
            $this->error('Partner provisioning is disabled.');

            return self::FAILURE;
        }
        ProvisioningIntent::query()->forIntegration((string) config('pulse.provisioning.integration', 'default'))
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('status', 'pending')->where('available_at', '<=', now());
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'processing')->where('leased_until', '<=', now());
                });
            })->orderBy('id')->chunkById(100, function ($intents): void {
                foreach ($intents as $intent) {
                    $intent->assertIntegration((string) config('pulse.provisioning.integration', 'default'));
                    ProcessProvisioningIntent::dispatch($intent->id);
                }
            });

        return self::SUCCESS;
    }
}

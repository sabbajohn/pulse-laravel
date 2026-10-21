<?php

namespace Sabbajohn\PulseLaravel\Provisioning;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessProvisioningIntent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public string $intentId)
    {
        $this->afterCommit();
    }

    public function handle(PartnerProvisioner $provisioner): void
    {
        if (config('pulse.provisioning.enabled', false)) {
            $provisioner->process($this->intentId);
        }
    }
}

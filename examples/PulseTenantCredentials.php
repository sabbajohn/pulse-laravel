<?php

declare(strict_types=1);

namespace App\Support;

final class PulseTenantCredentials
{
    public function resolve(): ?array
    {
        $tenant = tenant();

        if (! $tenant) {
            return null;
        }

        return [
            'base_url' => $tenant->pulse_base_url,
            'api_token' => $tenant->pulse_api_token,
            'timeout' => 30,
        ];
    }
}

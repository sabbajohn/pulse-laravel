<?php

namespace Sabbajohn\PulseLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PulseInstallCommand extends Command
{
    protected $signature = 'pulse:install {--force : Overwrite published files} {--write-env : Append missing PULSE_* keys to .env}';

    protected $description = 'Publish the VoraPulse Laravel SDK configuration, views, and assets.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->call('vendor:publish', [
            '--tag' => 'pulse-config',
            '--force' => $force,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'pulse-views',
            '--force' => $force,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'pulse-assets',
            '--force' => $force,
        ]);

        if ($this->option('write-env')) {
            $this->appendEnvDefaults();
        }

        $this->components->info('VoraPulse SDK installed.');
        $this->line('Set PULSE_BASE_URL and PULSE_API_TOKEN, or configure pulse.credentials_resolver for multi-tenant apps.');

        return self::SUCCESS;
    }

    private function appendEnvDefaults(): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            File::put($path, '');
        }

        $contents = File::get($path);
        $defaults = [
            'PULSE_BASE_URL' => 'https://pulse.seu-dominio.com',
            'PULSE_API_TOKEN' => '',
            'PULSE_ROUTE_PREFIX' => 'pulse',
            'PULSE_MIDDLEWARE' => 'web,auth',
        ];

        $lines = [];

        foreach ($defaults as $key => $value) {
            if (! str_contains($contents, $key.'=')) {
                $lines[] = "{$key}={$value}";
            }
        }

        if ($lines !== []) {
            File::append($path, PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL);
            $this->components->info('Missing PULSE_* keys appended to .env.');
        }
    }
}

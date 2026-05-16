<?php

namespace Sabbajohn\PulseLaravel;

use Illuminate\Support\ServiceProvider;
use Sabbajohn\PulseLaravel\Commands\PulseInstallCommand;
use Sabbajohn\PulsePhp\PulseClient;

class PulseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pulse.php', 'pulse');

        $this->app->singleton(PulseClient::class, function () {
            return new PulseClient(
                (string) config('pulse.base_url'),
                (string) config('pulse.api_token'),
                ['timeout' => (int) config('pulse.timeout', 30)],
            );
        });

        $this->app->alias(PulseClient::class, 'pulse');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PulseInstallCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/pulse.php' => config_path('pulse.php'),
            ], 'pulse-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/pulse'),
            ], 'pulse-views');

            $this->publishes([
                __DIR__.'/../publishable/assets' => public_path('vendor/pulse'),
            ], 'pulse-assets');
        }
    }
}

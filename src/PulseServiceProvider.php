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

        $this->app->singleton(PulseClientFactory::class, function ($app) {
            return new PulseClientFactory($app);
        });

        $this->app->bind(PulseClient::class, function ($app) {
            return $app->make(PulseClientFactory::class)->make();
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

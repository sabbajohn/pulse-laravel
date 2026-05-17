<?php

namespace Sabbajohn\PulseLaravel;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Sabbajohn\PulsePhp\PulseClient;

class PulseClientFactory
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function make(array $overrides = []): PulseClient
    {
        $resolved = $overrides === [] ? $this->resolveDynamicCredentials() : [];

        if ($resolved instanceof PulseClient) {
            return $resolved;
        }

        $credentials = array_replace($this->defaultCredentials(), $resolved, $overrides);
        $baseUrl = (string) ($credentials['base_url'] ?? '');
        $apiToken = (string) ($credentials['api_token'] ?? '');
        $timeout = (int) ($credentials['timeout'] ?? config('pulse.timeout', 30));
        $options = (array) ($credentials['options'] ?? []);

        return new PulseClient(
            $baseUrl,
            $apiToken,
            array_replace(['timeout' => $timeout], $options),
        );
    }

    private function defaultCredentials(): array
    {
        return [
            'base_url' => config('pulse.base_url'),
            'api_token' => config('pulse.api_token'),
            'timeout' => config('pulse.timeout', 30),
            'options' => config('pulse.options', []),
        ];
    }

    private function resolveDynamicCredentials(): array|PulseClient
    {
        $resolver = config('pulse.credentials_resolver');

        if ($resolver === null) {
            return [];
        }

        $resolved = $this->callResolver($resolver);

        if ($resolved === null) {
            return [];
        }

        if ($resolved instanceof PulseClient || is_array($resolved)) {
            return $resolved;
        }

        throw new InvalidArgumentException('Pulse credentials_resolver must return an array, PulseClient, or null.');
    }

    private function callResolver(mixed $resolver): mixed
    {
        if (is_string($resolver) && str_contains($resolver, '@')) {
            [$class, $method] = explode('@', $resolver, 2);

            return $this->container->call([$this->container->make($class), $method]);
        }

        if (is_string($resolver) && class_exists($resolver)) {
            $instance = $this->container->make($resolver);

            if (is_callable($instance)) {
                return $this->container->call($instance);
            }

            if (method_exists($instance, 'resolve')) {
                return $this->container->call([$instance, 'resolve']);
            }
        }

        if (is_callable($resolver)) {
            return $this->container->call($resolver);
        }

        throw new InvalidArgumentException('Pulse credentials_resolver is not callable.');
    }
}

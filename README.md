# sabbajohn/pulse-laravel

Laravel integration package for consuming the public `v2` VoraPulse API.

`pulse-laravel` is a framework adapter on top of `sabbajohn/pulse-php`. It does not extend the public API surface beyond the base client contract.

## Contract

The normative API contract is defined by:

- `https://github.com/vora-sys/Pulse/blob/main/VoraPulse/docs/openapi/pulse-public-v2.openapi.json`

## Compatibility

- PHP `^8.2`
- Laravel `9.x`, `10.x`, `11.x`, `12.x`, `13.x`
- `sabbajohn/pulse-php` `^0.1`

## Installation

```bash
composer require sabbajohn/pulse-laravel:^0.2
php artisan pulse:install --write-env
```

If Packagist is not yet indexing the latest tags, register the VCS repositories before requiring the packages.

## Configuration

Static fallback:

```env
PULSE_BASE_URL=https://pulse.seu-dominio.com
PULSE_API_TOKEN=seu-token
PULSE_ROUTE_PREFIX=pulse
PULSE_MIDDLEWARE=web,auth
```

The package automatically resolves the underlying `PulseClient` per resolution, preventing tenant credentials from leaking across long-running requests or jobs.

## Multi-tenancy

Use `pulse.credentials_resolver` when credentials depend on request, tenant or job context:

```php
'credentials_resolver' => App\Support\PulseTenantCredentials::class,
```

Supported resolver outputs:

- `null`: fall back to static config
- `array`: `base_url`, `api_token`, `timeout`, `options`
- `Sabbajohn\PulsePhp\PulseClient`: fully prebuilt client

## Usage

```php
use Sabbajohn\PulseLaravel\Facades\Pulse;

Pulse::emails()->sendAsync([
    'to' => [['email' => 'cliente@example.com']],
    'subject' => 'Processando',
    'html' => '<p>Recebemos sua solicitacao.</p>',
]);
```

For explicit runtime credentials:

```php
use Sabbajohn\PulseLaravel\PulseClientFactory;

$pulse = app(PulseClientFactory::class)->make([
    'base_url' => $tenant->pulse_base_url,
    'api_token' => $tenant->pulse_api_token,
]);
```

## Local UI and proxy

The local UI lives under `/{PULSE_ROUTE_PREFIX}` and offers a lightweight integration console.

The local proxy only forwards paths listed in `config/pulse.php` under `allowed_proxy_prefixes`. Administrative routes are blocked by design.

## Examples

- `examples/PulseTenantCredentials.php`

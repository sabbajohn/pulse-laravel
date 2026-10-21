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

Pulse::emails()->ingestBounce([
    'email_id' => 154,
    'bounce_type' => 'hard',
    'recipient' => 'cliente@example.com',
    'reason' => 'Mailbox unavailable',
]);

Pulse::campaigns()->update(42, [
    'name' => 'Newsletter atualizada',
    'channel' => 'email',
    'template_source_type' => 'html',
    'template_source' => '<p>Oi</p>',
    'compiled_html' => '<p>Oi</p>',
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
The default list includes the public domains such as `emails`, `templates`, `campaigns`, `contacts`, `audiences`, `automations`, `calendar` and `whatsapp`.
Because `emails` is allow-listed, the local proxy can also forward `POST /emails/bounces` for manual bounce ingestion.

## Examples

- `examples/PulseTenantCredentials.php`

## Durable partner provisioning (0.2.8)

Requires pulse-php ^0.1.5. The feature is disabled by default and has no automatic customer discovery or HTTP interception.

Update both packages with a targeted Composer update, review/commit the consumer's composer.json and composer.lock, then publish the opt-in migration:

```bash
php artisan vendor:publish --tag=pulse-provisioning-migrations
php artisan migrate
```

Configure server-only `PULSE_PARTNER_TOKEN`, `PULSE_PROVISIONING_BASE_URL` (HTTPS), a stable, byte-exact `PULSE_PROVISIONING_INTEGRATION` (case-sensitive even under case-insensitive DB collations), queue worker and periodic scanner before enabling `PULSE_PROVISIONING_ENABLED=true`. Never expose the partner token through Pulse's browser proxy. Existing proxy allowlists intentionally do not include provisioning.

Call inside the local customer's transaction, on the same default database connection:

```php
DB::transaction(function () use ($input) {
    $customer = Customer::create($input);
    app(\Sabbajohn\PulseLaravel\Provisioning\PartnerProvisioner::class)
        ->enqueue('customer-'.$customer->id, ['name' => $customer->name]);
});
```

Schedule `pulse:provision-pending` every minute to recover pending requests, queue outages and abandoned leases. HTTP occurs only after commit. Snapshot/result are encrypted with APP_KEY; jobs contain only intent UUIDs. HTTP timeout defaults to 15s (1–60 allowed), job timeout 90s, lease 120s; set queue retry_after above 120s. Rollback discards the intent. Timeout/429/5xx retry the same immutable key/payload; 401/403/404/409/422 stop as failed even for HTML/malformed responses. Transient retries honor Retry-After seconds or HTTP-date when later than the backoff. Monitor failures and backlogs without logging snapshots or credentials.

A `pulse.credentials_resolver` can use `PartnerProvisioner::credentialsFor($stableCustomerId)` from an authenticated, authorized customer context. All intent queries use a digest of the exact namespace bytes plus binary original comparison and the primary/write PDO, including the resolver/scanner. It throws for missing/pending/inactive results so it cannot fall back to another customer's static token. It resolves completed/active historical results only; current backend access remains authoritative. Default pending activation needs explicit operational review before adopting the local credentials. This package creates no human invitation and performs no SMTP checks.

For databases per tenant, explicitly design the local transaction boundary; this implementation uses the default connection and does not supply distributed transactions. Changing the namespace, partner or host for existing intents requires a reviewed migration. Full contract, activation limits and test commands: Pulse backend `docs/integracao/partner-provisioning.md`. Package tests run with that host's PHPUnit bootstrap; no real network is used.

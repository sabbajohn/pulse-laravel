# Changelog

## v0.2.5 - 2026-05-30

- Allowed `contacts` through the local proxy allow-list to match the public `/contacts` contract.
- Updated docs to include the new public contacts domain exposed by `pulse-php`.

## v0.2.4 - 2026-05-29

- Documented the OpenAPI public spec as the normative contract behind the Laravel adapter.
- Added proxy hardening and resolver-failure test coverage.
- Added explicit quickstart and release validation guidance.

## v0.2.3 - 2026-05-17

- Added Laravel 13 compatibility to the `illuminate/*` package constraints.
- Updated compatibility documentation and troubleshooting commands for Laravel 13.

## v0.2.2 - 2026-05-17

- Documented VCS repository configuration for `sabbajohn/pulse-php`.
- Documented a VCS fallback for installing the latest `pulse-laravel` tag before Packagist reindexing completes.

## v0.2.1 - 2026-05-17

- Added Laravel 9 compatibility to the `illuminate/*` package constraints.
- Documented supported Laravel versions and Composer troubleshooting commands.

## v0.2.0 - 2026-05-17

- Added `PulseClientFactory` for runtime client creation.
- Added `pulse.credentials_resolver` for multi-tenant credential resolution.
- Changed the Laravel `PulseClient` binding from singleton to per-resolution creation.
- Disabled facade root caching so tenant context changes do not reuse stale clients.
- Documented `.env` values as fallback configuration instead of the only source of credentials.

## v0.1.0

- Initial Laravel SDK package with service provider, facade, install command, local UI, and proxy routes.

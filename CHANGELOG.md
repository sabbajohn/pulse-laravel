# Changelog

## v0.2.0 - 2026-05-17

- Added `PulseClientFactory` for runtime client creation.
- Added `pulse.credentials_resolver` for multi-tenant credential resolution.
- Changed the Laravel `PulseClient` binding from singleton to per-resolution creation.
- Disabled facade root caching so tenant context changes do not reuse stale clients.
- Documented `.env` values as fallback configuration instead of the only source of credentials.

## v0.1.0

- Initial Laravel SDK package with service provider, facade, install command, local UI, and proxy routes.

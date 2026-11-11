# Phase 2 API foundation

Only `GET /api/v1/health` (and Laravel's implicit HEAD) is exposed by the application API
route file. No login, registration, token issuance, profile or business endpoints exist.

## Isolation

- Existing `routes/web.php`, controllers, Blade views and session/auth configuration are unchanged.
- `routes/api.php` is registered alongside web routes, with an additional `v1` group.
- The API group requests JSON before authentication. Exception envelopes apply only to
  `/api/v1` and descendants; web exception rendering falls through unchanged.
- Success uses `success`, `message`, `data`; errors use `success`, `message`, optional `errors`.
- API errors use generic messages and never return exception traces, even in debug mode.
  Standard `Allow` and `Retry-After` response headers are preserved.
- Health performs no database query and returns no user or environment information.

## Sanctum

Sanctum 4.4.0 supports the current Laravel 11 / PHP 8.2 requirement. Installation disables
Composer scripts/plugins to avoid unrelated publishing or application workflows. The provider
is registered explicitly so stale package-discovery caches do not require regeneration in the
original application. Other locked package versions are preserved.

The User model only gains `HasApiTokens`; existing traits, hashing and fields are retained.
`auth:sanctum` is ready for future protected route groups. It is not global middleware.
`sanctum.guard` is empty, disabling web-session fallback for mobile authentication. Stateful
SPA middleware and Sanctum's CSRF-cookie route are not enabled. The default web guard remains
`web`; existing web CSRF protection is retained.

The configurable token lifetime ceiling defaults to 43,200 minutes (30 days). Token issuance,
device management, explicit per-token expiry, account eligibility, roles, abilities and final
lifetime policy belong to the future authentication phase. Sanctum token authentication alone
does not enforce active/verified customer status or business authorization.

`2026_11_11_000001_create_personal_access_tokens_table.php` adds only Sanctum's token table.
The token is stored hashed; token owner/type, unique token and expiry indexes follow Sanctum's
published migration. The original database must not be migrated without separate approval.
Future protected operations against that database require the table to exist first.

## Connectivity and privacy

Use HTTPS and the Authorization Bearer header. Existing application CORS, cookie and CSRF
settings are unchanged; no wildcard credential policy or hardcoded mobile/server URL was added.
The existing public Apache rewrite file forwards Authorization headers. Actual Android/iOS
device connectivity and the deployed HTTPS proxy still require environment-specific testing.
Store future mobile tokens in platform secure storage, never logs or ordinary preferences.

Never serialize users or database rows directly in future public API responses. Counterpart
phone access requires authenticated, backend-verified acceptance for the relevant listing or
request. A chat room, login or `show_contact_info` flag alone is insufficient. The acceptance
workflow is not implemented in this phase; health and the response helper supply no user data.

## Future rate limits (not yet applied to absent auth routes)

Review limits with the auth implementation: login by normalized account plus IP; registration
by IP; reset/verification by account plus IP with resend cooldowns. The separate reset proposal
suggests a 60-second resend cooldown, five requests/account/hour, ten requests/IP/15 minutes
and five failed submissions/token/IP/15 minutes. Return 429 with Retry-After; do not reveal
account existence or log passwords, raw tokens or private contact details.

## Validation

Use the Phase 2A restricted test database and test bootstrap, never application credentials.
The single new migration may be applied there by its exact path after identity verification;
do not run historical migrations. Tests use synthetic records inside rollback transactions.

```powershell
php vendor/bin/phpunit --configuration phpunit.xml --filter 'ApiFoundationTest|SafetyEnvironmentTest' --do-not-cache-result --display-deprecations
```

The baseline fixture records all 80 pre-existing web/admin routes. Regression coverage compares
their methods, actions, names and middleware, checks session/CSRF configuration and retained
web error rendering. Test-only protected/error routes are registered solely inside tests and
are never in production route files. Full customer/admin browser workflows are not certified
by these foundation tests.

Validation on the confirmed local environment: 17 tests / 63 assertions passed, with one
existing PHP 8.4 deprecation in `voku/portable-ascii`. Only the new token-table migration was
applied to `friendie_logistics_testing_20261111_164222`; test records were rolled back. The
original database still has 25 tables, no token table, and identical schema/data fingerprints.
The Composer lock review found exactly one addition (Sanctum 4.4.0), no existing-package
version changes and no removals. Composer manifest validation passed. The lockfile audit
reported 76 advisories across 18 existing packages, with none reported for Sanctum.

## Remaining security work

Existing password-reset/activation and phone-disclosure weaknesses remain unchanged; see the
separate remediation proposal. Composer reports advisories in existing locked dependencies;
dependency upgrades require a separate reviewed scope. No production readiness is claimed.

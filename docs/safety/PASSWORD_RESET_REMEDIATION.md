# Password-reset remediation proposal — approval required

No authentication change is implemented by Phase 2A.

## Confirmed critical issue

`app/Http/Controllers/UserController.php:389` and
`app/Http/Controllers/AdminController.php:155` accept an account ID and slug, but update the
password without validating the slug against a stored, expiring, single-use token.
The mail template creates randomness during rendering; it does not persist an authoritative
token. Customer and admin reset endpoints share the same users table, so both need review.

## Proposed controlled implementation

- Reuse Laravel's password broker and existing `password_reset_tokens`; do not create a
  duplicate users table or return tokens in responses/logs.
- Generate cryptographically secure random tokens server-side and store only their hashes.
- Bind the token to the account's current email/identity and authorized customer/admin flow.
  Never authorize a reset from an ID or URL slug alone. Check roles server-side.
- Require current token validity and expiry before updating any password; use existing
  broker expiry configuration unless a separate policy change is approved.
- Ensure password update/token consumption is atomic and concurrent replays cannot succeed.
  Invalidate replaced/consumed tokens; handle concurrency explicitly in isolated tests.
- Validate password confirmation and policy, preserving Laravel-compatible hashing.
- Return neutral request responses that do not reveal account existence or role.
- Apply per-IP and per-account limits to requests and submissions. Initial proposed limits:
  request resend cooldown 60 seconds, 5 requests per account/hour, 10 requests/IP/15 minutes,
  5 failed submissions per token/IP/15 minutes; tune with abuse/availability testing.
- Never reactivate disabled accounts. Decide session/remember-token invalidation separately
  because it changes website behavior. No blanket password reset or data conversion.
- Preserve the existing web forms/redirect contract where possible; existing unsafe random
  slug links cannot remain valid as a security bypass. Document replacement-link behavior.

## Exact proposed file scope

Existing files requiring focused changes after approval:

```text
app/Http/Controllers/UserController.php
app/Http/Controllers/AdminController.php
app/Mail/UserForgotPasswrod.php
resources/views/frontend/mail/user-forgot-password-mail.blade.php
resources/views/frontend/forgot-password.blade.php
resources/views/admin/forgot-password.blade.php
routes/web.php
```

New proposed files:

```text
app/Services/WebPasswordResetService.php
app/Providers/WebAuthRateLimitServiceProvider.php
tests/Feature/Security/CustomerPasswordResetTest.php
tests/Feature/Security/AdminPasswordResetTest.php
```

Register the new rate-limit provider in `bootstrap/providers.php` only after approval. Change
`config/auth.php` only if broker policy changes are explicitly agreed. The existing table
can support the design; no schema migration is currently required by this proposal.

## Regression tests on the isolated synthetic-data database

- Correct customer/admin requests, forms, mail links and redirects.
- Unknown account and wrong-role requests receive equivalent neutral responses.
- Valid, missing, malformed, expired, replaced and replayed tokens.
- Token/account/email/route-role mismatches, including admin ID supplied to customer routes.
- Two concurrent reset attempts: one password update and one successful token consumption.
- Password confirmation, existing hashing, and login with the new password.
- Disabled accounts remain disabled; no unrelated profile/role changes.
- Rate limits, mail failures, no credential/token leakage and no external email.
- Session/remember behavior matches the separately approved policy.

The wildcard view composer (`app/Providers/AppServiceProvider.php:28`) can update expired
listings when rendering forms/mail. Capture this existing side effect in regression tests;
do not silently refactor it as part of the reset fix.

Email activation (`UserController.php:245`) has a related token-validation issue. Propose a
separate expiring verification flow and suspension-state review; do not treat password-reset
remediation as fixing activation or the existing phone-privacy workflow.

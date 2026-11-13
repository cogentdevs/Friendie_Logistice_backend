# Current Phase 3B implementation: approved Option A + Modified D

November 12, 2026. The reset fixes below are preserved. Secure activation, resend and
customer re-verification are implemented without schema changes. Implementation is tested,
but Phase 3B remains PARTIAL/release-blocked and no completion commit is created while the
production Blade-composer side effect and non-customer email workflow review remain unresolved.
The older sections below this update are historical and do not describe current activation.

## Approved account policy

- New public customer registration: isActive=1 / isVerified=0; no automatic login/token.
- Verification requires an active customer with isVerified=0 and changes only isVerified to
  1 plus email_verified_at to the actual new verification time. It never writes isActive or
  normal update timestamps. Existing verified customers retain access with null timestamps.
- Admin enable/disable continues setting isActive=1/0 through the existing admin form.
- Inactive unverified accounts are conservatively rejected. The original two ambiguous
  accounts are not classified, migrated or enabled. Login still requires both flags 1.
- Existing admin-created customer defaults remain unchanged (1/1).

## Signed 24-hour proof

`EmailActivationService` uses a cloned Laravel URL generator with trusted configured APP_URL,
never request Host or API_PUBLIC_BASE_URL. HTTPS is mandatory outside local/testing. No
configuration/environment file is changed. Local/test HTTP is explicitly allowed by environment.

The existing `/activate-your-account/{user_id}-{slug}` route is named `activation.confirm`.
The slug is SHA-256 of the current trimmed/lowercased email; it is identity binding, not the
secret. The URL signs that route ID/slug and `issued`, `expires`, `purpose` and uses Laravel's
HMAC signature. Purpose is `customer-email-activation-v1`. The framework expiration is supplied
as a DateTime value (not an integer interpreted as a relative delay).

Every GET/POST requires a valid Laravel signature, trusted origin, mandatory well-formed
integer timestamps, `expires-issued = 86400`, issued <= current server time, and current time
STRICTLY LESS THAN expires. Equality is rejected in addition to Laravel's native check. Time
is epoch seconds; client time is irrelevant. Delivery delay reduces remaining link lifetime.
Unknown query parameters, wrong purpose, wrong ID/email, missing/bad signature, malformed or
missing expiry, permanent signed URLs and legacy random slugs fail closed.

GET validates and displays the existing confirmation page without verifying. POST retains
web CSRF and rechecks proof, expiry, current email, exclusive customer role and current account
state under a user-row lock. One concurrent POST transitions; another returns already verified
without rewriting timestamps. The form preserves the signed URL/query. No tokens are issued.
Recovery responses use no-store/private and no-referrer; errors redirect to a fixed resend
page without echoing proof. Mail receives a fully generated URL; Blade no longer generates slugs.

## Stateless limitations

Signed URLs are NOT deleted/consumed. Idempotence comes from account state, not token storage.
Resending does not revoke earlier links. An email change rejects proof for the old normalized
email, but changing back within its original 24 hours can make an old signed proof applicable
again if the account is active and unverified. Disable/re-enable history cannot be reconstructed;
a still-valid link can apply after explicit administrative re-enablement. Stronger per-link or
history-based revocation requires separate approval for additional state. No hidden schema,
remember-token overloading, password-token reuse or new infrastructure is introduced.

## Resend and user experience

New web-only `GET/POST /resend-activation` uses the existing web session/CSRF group. Neutral
responses cover unknown, verified, disabled, wrong-role and eligible emails. Limits: 10/IP per
15 minutes, 5/normalized account per hour, 60-second resend cooldown; confirmation requests
are bounded to 30/IP per 15 minutes. 429 includes Retry-After. Only eligible active unverified
customers receive mail. Rendering/transport failures log a generic warning without recipient,
exception or signed URL. Tests fake outbound mail. Old insecure links cannot authorize any
transition and lead to the resend page; inactive legacy users require support/manual review.

## Customer email changes and the administrative boundary

Customer profile and admin-edited CUSTOMER emails use a row-locked helper. It validates email
and uniqueness before image processing, then revalidates under lock. A normalized email change
clears isVerified/email_verified_at without changing isActive, password, roles or remember token.
Only enabled customers receive a replacement verification email. Customer self-edit signs out
only the current device (without rotating remember tokens for other sessions); admin editing
keeps the administrator signed in. Other profile fields and existing enable/disable behavior
are preserved. A case-only email change does not reset verification. Existing other-device
sessions are not globally revoked; future sensitive operations must enforce current eligibility.

Admin/non-customer accounts have no approved customer activation flow. Changing their email
through this customer-management path is safely rejected BEFORE uploads/record updates; other
admin edits and login remain compatible. This narrow affected operation needs a separately
approved administrative email-change policy. It is not silently treated as customer verification
or allowed to remove an admin's access. No actual administrator account was changed.

## Browser/mobile compatibility

The same trusted HTTPS website link can be opened in any browser or on another device. The
future bare React Native app will use authoritative backend status/login results, never a
`verified=true` callback flag. No Android files, App Links, mobile registration/verification
API, Sanctum token issuance or public API contract was added/changed.

## Production view-composer release blocker

`AppServiceProvider.php:28` still has its existing wildcard composer that UPDATEs expired
listing statuses during Blade rendering, including mail/activation/resend pages. It is not
broadly refactored or silently fixed here. Focused auth tests remove that listener only in
their isolated app, supply read-only view data and mock Vite. This does not prove production
rendering is read-only. A narrowly approved composer fix or an approved independent read-only
rendering approach is REQUIRED before production release. No completion commit is created
while significant blockers remain, as instructed. No production workflow was exercised.

## Final focused verification

```powershell
php vendor/bin/phpunit --configuration phpunit.xml --filter 'EmailActivationTest|EmailActivationConcurrencyTest|WebPasswordResetTest|PasswordResetConcurrencyTest|ApiFoundationTest|PublicApiTest|SafetyEnvironmentTest' --do-not-cache-result --display-deprecations
```

117 tests / 1,058 assertions passed. One pre-existing PHP 8.4 voku/portable-ascii deprecation.
Coverage includes boundary equality, expired POST after GET, identity/purpose tampering,
permanent/future/wrong-lifetime signed links, legacy rejection, admin/disabled targets,
replay, real two-process concurrency, resend/rates/mail failure, email changes, login flags,
valid and missing CSRF, admin enable/disable and creation, password reset regressions,
80 original compatible web/admin routes plus the approved resend route, health and all
seven public APIs. The original activation route gains a name only; its path/method/action
remain intact. The original route fixture is preserved, with explicit approved differences
asserted in the foundation test. No historical migrations or production emails ran.

# Historical Phase 3B reports and proposal (superseded where noted above)

# Phase 3B status: password resets implemented; activation BLOCKED

Date: November 12, 2026. Changes remain uncommitted. The required completion commit is
withheld because the activation stop condition below prevents complete Phase 3B remediation.
The historical proposal follows this status update; its line references describe pre-fix code.

## Verified prerequisites

- Local-only target, original database `friendie_logistics`, MariaDB 10.4.32.
- Starting checkpoint `d6c01a4beb5fddc352f9a961514a3d429763eb82`; working tree was clean.
- Source/upload/configuration archives and current database dump checksums verified.
- All 25 original table schema/data fingerprints match the Phase 2A baseline.
- Isolated test database `friendie_logistics_testing_20261111_164222`; the common test
  bootstrap verifies its identity/grants and denies original-database access before writes.

## Customer and admin reset changes

Both web flows use `User`, the `web` session guard, and Spatie roles `user` / `admin`.
The former handlers ignored the URL slug and updated arbitrary IDs. Rendering generated a
random slug with no authoritative token record; email discovery responses differed. The
mail template inferred admin scope from ID 1, and the admin form posted to the customer URL.

The four existing controller methods now delegate to `Auth/WebPasswordResetController`.
Routes, methods, actions and middleware remain unchanged. The two reset views retain their
presentation/field names and display escaped validation errors; the admin action is corrected.
The existing mailable and configured transport remain in use. Its template receives the
already-issued URL instead of generating randomness. Mail errors receive a neutral response
and a generic warning without exception/recipient/token content. No real transport ran in tests.

`WebPasswordResetService` reuses Laravel's `DatabaseTokenRepository` for cryptographically
secure token generation and hashed storage in the existing `password_reset_tokens` table.
The stock global Password broker is not used unmodified: it does not supply role-separated
identity keys or atomic token-consumption locking. `ScopedResetIdentity` supplies a bounded,
versioned key in the existing `email` primary-key column:

`fl-reset-v1:<user|admin>:SHA256(account-id + NUL + current-account-email)`

These opaque identity keys are deliberately not mail recipients. Actual delivery uses the
matched account's email. No table/column/index migration, new broker/provider, second users
model/table, package or cache infrastructure was added. Plain-email stock/legacy token rows
cannot authorize these flows. A future framework-broker integration must preserve this
namespace/atomicity contract rather than silently bypassing it.

Tokens are 64 lowercase hex characters, bound to account ID/current email/role. Only accounts
with exactly the required web role qualify; mixed/missing roles fail closed. Admin ID 1 is
never special. Invalid, missing, expired, reissued, consumed, cross-account and cross-role
links fail without changing the password. Expiry follows `auth.passwords.users.expire`
(currently 60 minutes); missing creation timestamps fail closed. Issuance and reset lock the
same user row; reset locks and validates the current token row, hashes the new password and
consumes the token in one transaction. Real two-process tests verify one winner for both roles.

Password policy uses Laravel `Password::defaults()` (currently minimum 8 characters), exact
confirmation and a 72-byte maximum to avoid bcrypt truncation. Password fields are never
flashed. Password/status/role/session changes are limited to the authorized password and
normal update timestamp: no role/status/verification/remember-token rotation or blanket
session invalidation. Login with the new password works through existing controllers.
Disabled accounts remain disabled after password reset.

Request responses are neutral for known, unknown and wrong-role accounts. Limits use the
existing Laravel cache/rate limiter; cache keys hash account/IP identifiers, not raw tokens:

- requests: 10/IP/15 minutes and 5/normalized-account/hour, shared across customer/admin;
- resend: 60-second cooldown with the same neutral response for all submitted addresses;
- reset page access: 30/IP/15 minutes;
- submissions: 10/IP/15 minutes and 5/role/account/15 minutes, including failed attempts;
- 429 includes the actual available retry interval.

Recovery responses set no-store/private and no-referrer. URLs use trusted configured APP_URL,
not request Host, and preserve `/reset-password/{id}-{token}` / `/admin/reset-password/...`.
Invalid links redirect to fixed forgot-password pages, never echoing the supplied token.
Validation redirects reuse the valid reset form URL only as needed to correct form errors.
Previously sent random legacy slugs no longer authorize resets; request a replacement link.
Production APP_URL/HTTPS and web-server/access/analytics log redaction still need deployment
review; token-bearing links must be delivered in email and the reset form, never logged.
Neutral messages are not a claim of constant-time SMTP/account-lookup behavior.

## Activation STOP CONDITION: administrative disablement is ambiguous

Actual schema has `isActive`, `isVerified`, and `email_verified_at`, but no independent
administrative-disable marker/history. Existing code:

- `UserController.php:219-220`: registration sets isActive=0 AND isVerified=0.
- `UserController.php:150`: admin edit writes isActive, including 0, without recording why.
- `UserController.php:245-263`: activation ignores the slug and sets BOTH flags to 1.
- `frontend/mail/user-email-verification.blade.php`: random slug generated in Blade, no
  authoritative verification/expiry validation.
- `UserController.php:275`: customer login requires both flags; timestamp alone is not used.

Read-only aggregate inspection found TWO accounts with isActive=0/isVerified=0 and FIVE with
both flags 1. All existing email_verified_at values are null, including verified accounts.
Neither those fields nor timestamps can reliably classify the two inactive/unverified
accounts as pending versus administratively disabled. No customer data was printed/changed.

Per the user's explicit stop condition, activation implementation and its success/security
acceptance tests are BLOCKED, not passed. Activation/registration methods, verification mail,
activation view and admin enable/disable behavior remain unchanged; the existing activation
vulnerability remains critical. Do not deploy or describe Phase 3B as fully secure.

### Minimum proposal requiring separate approval (not implemented)

1. Add nullable `users.administratively_disabled_at` (or an equally explicit approved
   suspension marker). Update the existing admin enable/disable handler to record/clear it
   transactionally; pending registration remains distinguishable without trusting a client.
2. Require explicit owner review of existing ambiguous inactive/unverified accounts. A
   migration must not infer permission to reactivate them. A conservative legacy blocked
   state/backfill policy must be approved before enabling verification-driven activation.
3. Generate temporary signed verification links (e.g. 60 minutes), bind ID and current email,
   validate signature/expiry on GET and POST, preserve signed parameters in the form, and
   consume verification under a user-row lock. Reject legacy random-slug links. Only the
   verified, non-disabled, explicitly eligible pending state may become active; already
   verified/disabled states must not be changed by replay. Set verification timestamp without
   overwriting unrelated account fields.
4. Approve route-name/form/mail changes if needed while preserving current navigation.
5. On the isolated baseline plus only the specifically approved additive migration, test
   invalid/expired/tampered/cross-account/replayed links and disabling both before and after
   issuance, including unverified disabled accounts. Verify current login/registration and
   listing fingerprints again.

No migration has been generated/executed. The alternate schema-free design would verify
email without activating any isActive=0 account, requiring manual administrative approval;
that changes legitimate activation UX and also needs explicit approval. No silent switch to
that business policy was made.

## Mail/view testing boundary

The existing `AppServiceProvider` wildcard composer can UPDATE expired listings on any Blade
render, including mail. It is unchanged in production. Focused security tests remove just
that listener in their own isolated app and supply read-only shared view data; they also
mock Vite because isolated public storage has no production build manifest. They test the
real mail/form templates while faking outbound mail and preserving an expired listing fixture.
This guard does NOT certify that production mail rendering is free of that existing side
effect. A separate narrow composer remediation requires approval before that guarantee can
be made. Framework/public API tests continue using their normal isolated app setup.

## Verification

```powershell
php vendor/bin/phpunit --configuration phpunit.xml --filter 'WebPasswordResetTest|PasswordResetConcurrencyTest|ApiFoundationTest|PublicApiTest|SafetyEnvironmentTest' --do-not-cache-result --display-deprecations
```

Final selected run: 90 tests / 814 assertions passed; one pre-existing voku/portable-ascii
PHP 8.4 deprecation. Coverage includes both roles, valid/invalid/expired/replay/account/role
isolation, confirmation/policy, reissue/email change, null timestamps, neutral responses,
rate limits, two real concurrent processes per role, role/permission/status/session
preservation, old/new-password login, registration with synthetic CAPTCHA, real CSRF rejection,
mail transport failure, form/mail rendering, unchanged 80 routes and all Phase 2/3 API tests.
Activation tests remain BLOCKED by the schema/business-state decision, not counted as passed.
No historical migration suite, production workflow or real email was executed.

## Historical pre-implementation proposal

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

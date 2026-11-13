# Mobile authentication API v1

Backend-only Phase 4. The seven public browsing APIs and `/api/v1/health` remain public and unchanged. All auth responses use the existing JSON envelope. No native verification endpoint or mobile app changes are included.

## Deployment gate

The original `friendie_logistics` database has **not** been migrated or used for these tests. Mobile login requires the existing Sanctum migration, which is already present in the isolated test schema. After separate owner approval, a fresh verified backup and identity checks, the operator must run **only**:

```powershell
php artisan migrate --path=database/migrations/2026_11_11_000001_create_personal_access_tokens_table.php
```

Do not run historical migrations. Do not run this command as part of testing or without approval. Until the original token table is installed, real-device login is not ready and attempts to issue/authenticate tokens can return a sanitized 500. Registration/recovery still change account records when used normally, so they were not exercised against the original database.

## Headers and response contract

Use HTTPS in production with `Accept: application/json` and `Content-Type: application/json`. Protected routes also require `Authorization: Bearer <access-token>`. No cookie, session or CSRF token is required for these API routes. Existing web CSRF/session behavior is unchanged.

Success: `{"success":true,"message":"...","data":{}}`.
Validation: HTTP 422, `{"success":false,"message":"Validation failed","errors":{"email":["..."]}}`.
Invalid credentials: HTTP 401, `{"success":false,"message":"Invalid credentials."}`.
Missing/invalid/expired/revoked bearer token: HTTP 401, message `Unauthenticated`.
Authenticated but ineligible account or wrong token ability: HTTP 403, message `Forbidden`.
Throttle: HTTP 429, message `Too many requests`, with `Retry-After` seconds.
Unexpected errors: sanitized HTTP 500, message `Server error`.

Extra fields on auth submissions that accept input are rejected with 422, including client-controlled role/status fields. The current-user and logout endpoints do not use client account IDs. Empty successful `data` is an object. Login and current-user responses are marked private/no-store. Tokens and recovery proofs must be redacted from infrastructure logs, analytics, crash reports and Postman exports; production account mail must not use a logging transport.

## Endpoints

### POST /api/v1/auth/register

```json
{
  "name": "Example Customer",
  "email": "customer@example.test",
  "phone": "+10000000001",
  "country_id": 1,
  "password": "ExamplePassword123!",
  "password_confirmation": "ExamplePassword123!",
  "captcha_token": "<fresh-provider-challenge-response>"
}
```

All fields required. Phone and country preserve the existing web registration inputs; country must be an active existing country. Name/email/phone max 255 characters. Password follows the existing Laravel policy (currently minimum eight characters), confirmation required, maximum 72 bytes for bcrypt. Email is trimmed/lowercased; existing database uniqueness rules apply to email and phone.

The customer role is assigned server-side; status is `isActive=1,isVerified=0`, with no session or token issued. The account and role are created transactionally. After commit, the existing activation service generates the same signed 24-hour browser link. Mail failure leaves an unverified account recoverable through resend; no exception or proof is returned.

HTTP 201 returns the same neutral message and empty object for newly created or duplicate email/phone details:

```json
{"success":true,"message":"If registration is available for these details, a verification email will be sent. Please check your inbox and spam folder.","data":{}}
```

Duplicate details deliberately do not return an enumerable uniqueness error. Abuse protection: five requests per IP/hour plus the existing server-verified CAPTCHA. The API does **not** bypass or disable web CAPTCHA. Bare React Native must supply a valid response obtained through a compatible hosted challenge flow; a native attestation replacement requires a separately approved design. A four-field registration request is insufficient. Never ship a fixed or test CAPTCHA bypass.

### POST /api/v1/auth/login

Input: `{"email":"customer@example.test","password":"ExamplePassword123!"}`.
Only exactly-one-role web customers with both status flags equal to 1 qualify. Unknown email, incorrect password, admin, mixed-role, disabled and unverified accounts receive the same 401 message. No website guard/session is used or modified.

```json
{"success":true,"message":"Login successful.","data":{"token":"<access-token>","token_type":"Bearer","user":{"id":1,"name":"Example Customer","email":"customer@example.test"}}}
```

Sanctum stores only the token hash. Tokens have the `mobile:account` ability and an explicit expiry from `sanctum.expiration` (currently 43,200 minutes / 30 days); configuration must be positive. No refresh-token endpoint exists. Login limits: five attempts/email/15 minutes and twenty/IP/15 minutes; failures and successes count.

### GET /api/v1/auth/me

Bearer token required. Response: `{"success":true,"message":"Account retrieved successfully.","data":{"id":1,"name":"Example Customer","email":"customer@example.test"}}`.

Explicit allowlist only. Role and active/verified status are rechecked on every request. An old token cannot bypass later disablement/unverification/role changes. The token must be a real Sanctum personal access token with the mobile account ability; web sessions cannot substitute. No arbitrary account ID is accepted. Own email is returned; phone, password, remember/reset tokens and administrative fields are excluded. Authentication does not authorize access to any other customer's phone.

### POST /api/v1/auth/logout

Bearer token required; empty body. HTTP 200: `{"success":true,"message":"Logout successful.","data":{}}`.
Deletes only the current access token; other devices and web sessions remain intact. Missing/revoked tokens return 401. Ineligible accounts return 403 even on logout; the token grants no access, but server-side administrative token management remains a future concern.

### POST /api/v1/auth/forgot-password

Input: `{"email":"customer@example.test"}`.
HTTP 200 neutral response for known/unknown/wrong-role accounts:

```json
{"success":true,"message":"If an eligible account matches that email, a link will be sent. Please check your inbox and spam folder.","data":{}}
```

Uses the shared customer-scoped hashed reset repository, current email binding, existing 60-minute expiry and atomic consumption. Mail contains the existing browser reset URL; the browser is the supported default mobile recovery experience. No proof is returned by the API. Mail failures are neutral. Shared web/API limits: ten requests/IP/15 minutes, five/email/hour, sixty-second send cooldown, including nonexistent accounts.

### POST /api/v1/auth/reset-password

```json
{"user_id":1,"token":"<64-lowercase-hex-reset-proof>","password":"NewExamplePassword123!","password_confirmation":"NewExamplePassword123!"}
```

This contract derives from the existing browser link `/reset-password/{user_id}-{token}`. Only a user who possesses the emailed proof can submit it; ID alone never authorizes reset. A future native reset screen would need an approved link-handling flow. Do not expose/obtain tokens from another API or change the browser security model.

Success HTTP 200: `{"success":true,"message":"Password reset successfully.","data":{}}`.
Well-formed but invalid/expired/replayed/cross-account/cross-role proof: HTTP 422, `{"success":false,"message":"Invalid or expired reset link."}`. Malformed proof and password validation return standard field errors. Account row and token consumption are serialized in the existing service, preventing concurrent reuse. Does not change statuses, roles, website sessions or other-device tokens; does not log in or issue a token. Existing access tokens are not revoked by a reset under the currently approved policy.

Shared web/API limits: ten submits/IP/15 minutes, five/customer ID/15 minutes. Password policy matches registration and the secure web reset policy.

### POST /api/v1/auth/resend-verification

Input: `{"email":"customer@example.test"}`. Same HTTP 200 neutral response as forgot-password, regardless of account existence/eligibility or cooldown. Only active, unverified, exclusively customer accounts receive mail. Shared web/API limits: ten/IP/15 minutes, five/email/hour, sixty-second cooldown.

Uses the existing trusted APP_URL-based signed activation proof, account/current-email/purpose binding and exactly 86,400-second lifetime. Browser GET confirms without writing; CSRF-protected browser POST revalidates and updates verification only. Verification never enables disabled accounts or issues tokens. At expiry equality the link is invalid. Resend does not revoke earlier stateless signed links. The future app should attempt authoritative backend login/status retrieval after browser verification; never trust `verified=true` or device time.

## Mail and existing composer limitation

API-initiated activation/recovery emails use `MobileAccountMail`, escaped pre-rendered HTML with the shared services' generated trusted URLs and configured mail transport. They do not render Blade or run wildcard view composers. Optional delivery callbacks leave the existing web mail behavior unchanged. Production account links require HTTPS; local/testing may use a configured trusted HTTP origin.

The existing web wildcard Blade composer can still update listing statuses during ordinary web/mail rendering. It was deliberately **not changed** in Phase 4. Browser confirmation/reset pages may invoke it. This remains a production release blocker requiring the separate expiry/business/scheduler decision from Phase 3C; API-only mail avoids the side effect but does not resolve it globally.

## Postman and React Native integration

1. Use only synthetic accounts on the isolated environment for tests that mutate data. Obtain an active country via `/api/v1/countries` and a fresh valid CAPTCHA response.
2. Register; expect 201 and no token. Open the emailed canonical browser URL and confirm using its CSRF-protected form. Then log in explicitly.
3. Keep the returned token in a private Postman variable; use Bearer Token for `/auth/me` and `/auth/logout`. Never export live tokens.
4. Exercise forgot-password; complete the emailed browser form or supply its ID/proof to the reset API. Confirm replay is rejected. Test resend after sixty seconds.
5. After logout, that token must return 401; another device token must remain usable.

Bare React Native should store tokens using OS Keychain/Keystore-backed secure storage, never AsyncStorage/plain files, console logs or analytics. Remove local tokens on logout and on authoritative 401; clear private cached data. Handle 403 as account ineligibility; 429 obeys Retry-After. No automatic refresh is implemented. HTTPS and a device-reachable canonical backend/APP_URL are mandatory for release; device localhost is not the PC. Deep linking/native CAPTCHA integration and physical-device testing are not implemented here.

## Verification scope

Focused tests use only the restricted isolated database and synthetic transactional records; mail and external side effects are faked. The shared reset concurrency tests remain part of regression verification. Existing 80 baseline web/admin routes plus the approved resend route must remain compatible; only seven auth routes are added. No original-database migration, live password reset, real email or device test is part of this change.

Phase 4 verification: 152 tests / 1,280 assertions passed across mobile auth, web reset/activation (including concurrency), foundation, public API and safety tests. One existing PHP 8.4 deprecation remains in `voku/portable-ascii`. Eight changed PHP files passed syntax checks. The isolated Sanctum table's columns/indexes and existing migration record were checked; migration `up()` was additionally run in Laravel pretend mode only (four generated statements, no DDL execution). The full historical migration-based suite was not run.

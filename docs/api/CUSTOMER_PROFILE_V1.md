# Customer dashboard and profile API v1

Phase 5 adds six backend-only endpoints. Existing Phase 3 public browsing and Phase 4 authentication contracts are unchanged. No migrations, new columns, new disks, app changes or original-database tests are included.

## Authentication and common responses

Base path: `/api/v1/customer`. Use HTTPS and headers:

```text
Accept: application/json
Authorization: Bearer <access-token>
```

For JSON bodies also send `Content-Type: application/json`. Obtain a token through the existing `/api/v1/auth/login`. Every route uses `auth:sanctum` and `EligibleMobileCustomer`; the account must currently be active, verified and exclusively a web customer role, with `mobile:account` ability. A web session is not a substitute. An account ID is never accepted to select a profile.

Success is HTTP 200 with `success`, `message`, `data`. Validation is HTTP 422 using the established message `Validation failed` and an `errors` object. Missing/invalid/expired/revoked bearer tokens return 401 `Unauthenticated`; ineligible accounts or wrong ability return 403 `Forbidden`. Limits return 429 `Too many requests` with `Retry-After`. Unexpected exceptions return sanitized 500 `Server error`. No passwords, hashes or internal paths are returned.

Example field error:

```json
{"success":false,"message":"Validation failed","errors":{"phone":["The phone has already been taken."]}}
```

## GET /api/v1/customer/dashboard

No query parameters or body. Returns compact account-scoped aggregates, never rows from other customers.

```json
{
  "success": true,
  "message": "Dashboard retrieved successfully.",
  "data": {
    "listings": {"total": 5, "published": 2, "pending": 1, "expired": 2},
    "unread_messages": 3,
    "reviews_received": 1
  }
}
```

Definitions:

| Field | Exact meaning |
| --- | --- |
| `listings.total` | All `post_ads` where `created_by` is the authenticated user |
| `published` | Those records with stored `isActive=1` |
| `pending` | Stored `isActive=0` |
| `expired` | Stored `isActive=2` |
| `unread_messages` | Messages addressed to this user with NULL `read_by`, joined to an existing room/listing, with sender and recipient matching the room's two participants |
| `reviews_received` | Existing reviews with `to_user_id` equal to this customer |

Listing categories are **stored-status counts**, consistent with the current web dashboard semantics. This is not a public-visibility count: a past-departure status-1 record is still counted as published here, but public listing APIs exclude it. No expiry/status synchronization occurs. Unknown legacy status values count toward total only. No request/acceptance count or profile-completion percentage is invented; the schema/policy does not support them reliably. Three aggregate queries calculate metrics; Sanctum/account checks add a bounded number of authorization queries, not one query per listing/message.

Both customer GET routes use the installed Sanctum `Guard` with `trackLastUsedAt:false` before normal `auth:sanctum`. This avoids even its normal token `last_used_at` write. The framework's authentication marker ensures correct middleware order. Signature/hash, provider, expiry and eligibility checks still apply. No global guard/configuration or Phase 4 token-use tracking changes. Tests require all SQL during these GET requests to be SELECT-only and no Blade composer invocation.

## GET /api/v1/customer/profile

No query parameters or body. Explicit own-profile allowlist:

```json
{
  "success": true,
  "message": "Profile retrieved successfully.",
  "data": {
    "id": 1,
    "name": "Example Customer",
    "email": "customer@example.test",
    "phone": "+10000000001",
    "country_id": 1,
    "country": {"id": 1, "name": "Example Country", "short_name": "EX"},
    "image_url": null,
    "is_verified": true,
    "email_verified_at": null
  }
}
```

Phone/email belong only to the authenticated customer. Other users' phone numbers are never included. Country may be NULL for missing legacy geography. Image URL uses the existing trusted `api.public_base_url` / APP_URL fallback and public `frontend/img/profiles` directory; missing/unsafe images produce NULL. Existing verified accounts can legitimately have a NULL verification timestamp. No role, administrative status, remember token or password/reset proof is serialized. GET responses are private/no-store.

## PUT /api/v1/customer/profile

Partial update; supply at least one supported field. All provided values must be nonempty and valid. Extra/privileged fields (including ID, roles, statuses, password and image filenames) are rejected. Unsubmitted fields and unrelated database columns remain unchanged.

```json
{"name":"Updated Customer","email":"new@example.test","phone":"+10000000003","country_id":1}
```

| Field | Validation |
| --- | --- |
| `name` | String, max 255 characters |
| `email` | Valid string email, max 255; shared service normalizes and checks uniqueness |
| `phone` | Nonempty string, max 255, unique excluding own account; no E.164-specific policy added |
| `country_id` | Positive integer for an active existing country |

Success data contains `profile` (same allowlist as GET), `verification_required` and `reauthentication_required`. Ordinary updates set the latter two to false and keep tokens. A normalized email change returns true for both and message `Profile updated. Verify your new email and sign in again.` Case-only normalization does not force re-verification.

Email handling uses the existing `CustomerEmailChangeService`: clear `isVerified` and `email_verified_at`, preserve `isActive`, and send the existing signed 24-hour browser verification link through API-safe `MobileAccountMail` after the account transaction commits. Old-email links fail current-email binding. All of this customer's personal access tokens are revoked atomically with an API email change, including current token and devices with nonstandard token names/abilities. The other customer's tokens and existing web sessions are not changed. Old mobile tokens remain revoked even after re-verification; log in again for a new one. No replacement token is returned.

Mail failure leaves the change committed and account unverified/tokens revoked. Use public `/api/v1/auth/resend-verification` if needed. Only an eligible active unverified customer receives mail. Stateless resend does not individually revoke earlier links; changing an email away and back within a link's lifetime retains the existing documented signed-link limitation. Admin email restrictions and default web workflows are unchanged. Web-initiated email changes retain their existing policy; this new token-revocation policy applies to Phase 5 mobile mutations.

Limit: ten profile update attempts per account and IP per 15 minutes. Account row locks serialize mutations; eligibility and current token existence are rechecked inside the transaction. Duplicate-update races return a safe 422.

## POST /api/v1/customer/profile/avatar

Use Postman **Body → form-data**, key **`avatar`**, type **File**. Let the client set the multipart boundary; do not manually set Content-Type to JSON.

Requirements: JPEG, PNG or WebP extension and detected MIME/content; max 2,048 KiB; width/height 1–2,048 pixels. SVG, GIF, executable extensions, spoofed content, extra fields and oversized files are rejected with 422. The existing GD/Intervention decoder must successfully decode the image. Output is re-encoded as JPEG (max 512×512, no upscaling), so original executable payload/metadata is not copied verbatim.

Success:

```json
{"success":true,"message":"Avatar updated successfully.","data":{"image_url":"https://media.example.test/frontend/img/profiles/api_user_1_<random>.jpg"}}
```

Filename is server-controlled using account ID and 128 random bits. A new file is created exclusively under the existing resolved public profile directory. No client path/origin is used. The directory must already exist, be writable, and resolve inside public; otherwise fail safely with sanitized 500. Account update is transactional, and a database failure removes only the brand-new file from that request. Old/default/shared files are never deleted. File storage failure leaves the original reference intact. Database and filesystem are not a single crash-atomic storage system; process interruption can leave an unreferenced generated file. No background cleanup is introduced.

## DELETE /api/v1/customer/profile/avatar

Empty body, no account ID. Resets only this customer's `image` reference to the existing `avatar.jpg` default; no physical old image deletion. Repeated removal and missing old files are safe. Default/shared/other-user files are preserved. Response is `Avatar removed successfully.` with `data.image_url` for the default, or NULL when that file/configuration is unavailable.

Upload/removal share a limit of ten attempts per account and IP per hour. Retained former avatars can remain accessible at their previous public URLs; this is reference removal, not guaranteed media erasure. Disk growth/physical erasure needs a separately reviewed ownership-aware cleanup policy. There is no new storage disk, symlink, migration or upload infrastructure.

## POST /api/v1/customer/change-password

```json
{"current_password":"CurrentExamplePassword123!","password":"NewExamplePassword123!","password_confirmation":"NewExamplePassword123!"}
```

Current password must match. New password must differ, match confirmation, satisfy the existing Laravel secure reset policy (currently minimum eight characters) and be at most 72 bytes for bcrypt. Invalid current password or confirmation/policy errors return 422; no hash change or token revocation occurs on failure. Five attempts per account and IP per 15 minutes, including failures.

```json
{"success":true,"message":"Password changed successfully. Sign in again.","data":{"reauthentication_required":true}}
```

The password is hashed and updated under an account row lock. Every personal access token owned by this customer is revoked in the same transaction, regardless of device name/ability. An outstanding customer-scoped password-reset proof is deleted using the existing reset service identity. The current token must not be used again; it returns 401. No automatic login or replacement token. Other accounts, roles, activation flags, remember tokens and existing website sessions are preserved. Website-session invalidation requires a separate policy decision; this endpoint does not log out those sessions. Existing Phase 4 forgot/reset endpoints retain their approved policy and contracts.

## Postman and mobile sequence

1. Use an isolated backend and synthetic active verified customer. Log in with `/api/v1/auth/login` and copy the token into a private Postman Bearer variable.
2. GET dashboard/profile; confirm only your own phone/email are returned.
3. PUT a name/phone update. PUT an email change separately: expect verification/reauthentication flags, receive browser email, and expect the old token to fail. Verify and log in explicitly again.
4. Upload a file through multipart `avatar`. Open the returned URL from the intended device. DELETE resets the reference; it does not erase the old public file.
5. Change password. Clear stored token, then log in explicitly using the new password.

The future mobile app must use OS secure token storage, clear local tokens/private cached data after email/password security changes, and respect 401/403/429. No React Native or Android files are modified here. API/media/browser-link origins must be device-reachable and HTTPS in production; do not use device loopback as the development PC's address.

## Safety and remaining release dependencies

Tests use the existing restricted actual-schema test database, transactional synthetic fixtures, fake mail/storage and isolated temporary public paths. Original database records, uploads, `.env`, web/admin routes and the Blade composer are protected. No migrations are executed. The original database still requires its separately approved Sanctum token-table migration before real mobile token operations. The existing wildcard Blade composer remains a separate browser/mail side-effect issue; API controllers and API activation delivery do not invoke it. No listing management, acceptance or phone-reveal API is added.

Only a viewer's **own** phone is exposed by profile. Login alone still never authorizes another customer's phone; accepted-request authorization remains a separate future workflow.

## Verification record

The focused Phase 5 suite adds 44 tests / 374 assertions. The combined Phase 2/3/3B/4/5 safety, public API and authentication selection passed **196 tests / 1,654 assertions**, including real shared reset/activation concurrency tests and a failed-email-update transaction test proving no premature mail delivery. One pre-existing `voku/portable-ascii` PHP 8.4 deprecation remains. The full historical migration-based suite was not run. Seven changed PHP files passed syntax checks; web/admin route comparison retains the 80 baseline routes plus the approved resend route. Total API inventory is now 21 method/path endpoints.

Before/after integrity checks matched the current original database fingerprint (all 25 tables), 43 baseline media hashes and upload-directory inventory. The existing staged index, `.env`, Composer files, web/admin controllers/routes/views and other protected source files were preserved; the only shared source extension is the optional email-delivery callback/after-commit dispatch in `CustomerEmailChangeService`. Test users, listings, rooms, messages, reviews, reset proofs and tokens were rolled back in the restricted test database. No original-database migration or real email was sent. Previous staged Phase 3B/4 work and unstaged/untracked Phase 5 work remain separate; no commit/push is included.

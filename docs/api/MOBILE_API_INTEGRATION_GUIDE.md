# Friendie Logistics — Mobile API Integration Guide

Audited 14 November 2026. Guidance for **bare React Native CLI, not Expo**. No mobile code, Android configuration, dependency installation or actual integration was performed. Read FINAL_API_AUDIT_V1.md first: implemented/tested APIs are not equivalent to a production-ready shared backend.

## Readiness gate

There are **37 routes**, including one intentionally blocked listing DELETE. The original local database still lacks `personal_access_tokens`; live token login/protected calls are not deployable there. Tests use its separately prepared isolated table. No migration is authorized/executed in this phase. Review pending-listing disclosure, shared website authorization/XSS, password-reset token policy and privacy decisions before release.

## Origins, HTTPS and media

- Configure one device-reachable API root ending `/api/v1`. Example only: `https://api.example.test/api/v1`. Do not append the prefix twice.
- Windows localhost/127.0.0.1 addresses this computer; a device's loopback addresses the device. Standard Android emulator can use its host alias 10.0.2.2; physical devices need reachable LAN/development hostname or public HTTPS origin. A server listening only on loopback/firewall may prevent access. No binding/firewall/CORS/network-security changes were made.
- Production requires trusted HTTPS, valid certificate chain and owner-managed mail/web origins. Do not bypass TLS validation or embed production secrets. Native bearer clients need no cookie/CSRF bootstrap and no session-based SPA setup.
- `API_PUBLIC_BASE_URL` is the public application/media root, **without** `/api/v1`, image subpath or filesystem path. Existing serializer appends actual public directory paths. When absent, APP_URL is used. Configure origins through an approved environment/release process, not request Host headers.
- Existing selected development media origin is loopback: physical-device reachability remains NOT VERIFIED. Backend tests validate URL construction, not Wi-Fi routing, Android cleartext policy or actual remote availability. Open returned image URL on intended device and check HTTP200/image MIME.
- Missing/unsafe image returns null; handle it in UI. Do not reveal local paths. Avatar and listing image removals reset references, not physical deletion; old public URLs may remain accessible.

## Headers and response envelopes

```text
Accept: application/json
Content-Type: application/json
Authorization: Bearer <private customer token>
```

Authorization is omitted for guest/auth-public requests. File uploads use multipart/form-data with generated boundary, not JSON Content-Type. **Profile avatar file key is `avatar`; listing file key is `image`.** Earlier overview docs incorrectly described avatar as image; this guide and the final collection match the controller.

Success: `{success:true,message,data}`. Validation422 includes `errors`. Pagination is nested inside data; do not expect top-level meta/links. Do not branch application logic on English message text where status/fields are available.

## Guest launch and references

GET health/home/categories/countries/cities/currencies/listings/listing details are public. No token, session or registration gate should block initial browsing.

Categories/countries/currencies accept optional search; cities also accepts country_id. Results contain complete matching `data.items` with **no pagination or silent limit**. page/per_page/all are unsupported. Search is trimmed literal case-insensitive LIKE, ranking exact then prefix then substring; wildcard characters are literal. Unfiltered city data can be large: search/country-filter/debounce/cache client-side without assuming server truncation.

Public listings support only verified directional fields, search, type, weight, exact departure, sort and pagination. Generic country_id/city_id are unsupported. Eligible listings require published1, active category, departure >= today and required joins. Featured home additionally requires >today. Current original listings are historical; empty results do not authorize republishing data. Private/ineligible details return404.

## Registration, verification and login

Registration requires name/email/phone/active country/password/password_confirmation/**fresh CAPTCHA token**. The server still verifies CAPTCHA; the app needs an approved hosted challenge/native-compatible design. A four-field registration screen alone cannot call this contract successfully. No global CAPTCHA bypass exists.

POST auth/register returns neutral201, creates active1/unverified0 customer when eligible, sends canonical signed browser activation and issues **no token**. Duplicate email/phone details receive neutral confirmation. Mail failure can be recovered with resend; neutral success does not prove delivery.

Verification is browser-based: signed GET displays confirmation without activation, CSRF-protected POST revalidates proof and changes verification only. Lifetime exactly86400seconds; invalid at equality. Account ID/current normalized email/purpose/origin bound. Disabled accounts are not enabled. Resend has cooldown and rate limits; previous stateless links remain valid until original expiry unless current email/state independently invalidates them. No native verify-email API or Android App Links are implemented.

After verification, explicitly POST auth/login with email/password. Only active, verified, exclusively customer accounts receive `{token,token_type,user}`. Admin/mixed-role/disabled/unverified/unknown/wrong-password return the same401. No website session dependency. Do not trust an untrusted `verified=true` redirect parameter; backend authentication/status is authoritative.

Token ability is mobile:account; configured default expiry **43200 minutes / 30 days**. No refresh-token endpoint exists. Account eligibility is rechecked for protected requests. Original token-table installation is a separate owner-approved deployment gate; do not invoke general migration commands from mobile setup.

## Secure token lifecycle

Use OS Keychain/Keystore-backed secure storage. Do not store tokens in AsyncStorage/plain files, screenshots, console output, analytics or crash reports. Do not ship client-side backend secrets or real sample credentials. Collection token is empty and no automatic capture/logging script is supplied.

On logout revoke current token through API, then clear local token/private caches. Other devices and website sessions remain intact. Missing/revoked/expired token returns401; prompt explicit reauthentication. For403 handle account ineligibility without repeatedly retrying.

Profile email changes revoke all account tokens and require browser verification plus login. Customer change-password revokes all personal tokens and outstanding reset proof, with reauthentication_required=true. **Forgot/reset-password currently does not revoke existing mobile tokens or web sessions**: this inconsistency is a high audit finding, not a security guarantee the app can fix.

GET auth/me currently uses ordinary Sanctum and may update token last_used_at. Customer dashboard/profile/listing/chat/review GETs use read-only token middleware. No GET marks chat read.

## Password recovery

Forgot-password returns neutral200 and sends a browser reset URL for eligible customers. Reset lifetime60minutes, hashed role/account/current-email proof and atomic single-use consumption. No proof is returned by API.

Default mobile experience is the emailed browser form. Native reset endpoint also exists with user_id, emailed64hex token, password and confirmation; obtaining that proof needs an independently approved link-handling experience. Never reset using ID alone or fetch proof from another endpoint. No automatic token/login after reset. Resend/recovery cooldown is60seconds; respect429 Retry-After.

## Own dashboard/profile

GET dashboard returns own stored-status listing counts, authorized unread message count and reviews received. Published count is not a public-visibility count; no GET expiry synchronization. No request-acceptance metrics exist.

GET profile returns only own allowed profile fields, including own email/phone. PUT supports name/email/phone/country_id; response is **data.profile** plus verification_required and reauthentication_required. Never echo status/role/password fields back. Email change preserves administrative activation and requires re-verification.

Avatar multipart key avatar; JPEG/PNG/WebP <=2048KiB, each dimension <=2048. Output safe JPEG and trusted URL. DELETE avatar resets reference to default. No physical-erasure promise. Password change needs current_password, password, password_confirmation.

## Listing management

Owner list/create/detail/partial update and single-image upload/reference removal exist. List includes own pending0/published1/expired2, with filters and pagination. New records are pending0; clients cannot set owner/status/featured. Carring spelling is intentional; Send uses same verified required field set. Country/city membership, currency reference, required dates, numeric/text constraints apply. Arrival must not precede departure; API does not invent a future-only creation rule.

Owner detail can show pending/expired records that public detail hides. Update preserves stored status; changing expired dates does not republish. Non-owned IDs return404. Listing image uses multipart image, same safe types/limits; old files remain.

**DELETE listing is NOT implemented as deletion or archival. It returns409 for owned records.** Do not display successful deletion, remove history or assume archive support. No archived_at/deleted_at/acceptance field is authorized.

Listing descriptions can contain legacy HTML and existing website renders it unsafely; see high XSS finding. React Native should treat received content as untrusted text and never inject it into an unrestricted WebView. This client practice does not fix the shared website sink.

## Chat usage

Six protected routes list/show conversations, paginate messages, create/reuse room, send text and mark incoming messages read. Creation accepts post_ads_id and first message; sender/recipient are server-derived. New room requires eligible owner and future public listing. Existing room reuse returns200 without duplicating first message; new room returns201. Continue via messages endpoint after reuse.

Verify response conversation ID; never accept another user ID as authority. History oldest message ID first; conversations latest valid message ID first. GET does not mark read. POST read is explicit and idempotent, setting NULL read_by only for valid incoming messages to caller. Existing non-null values preserved. Counts reflect transaction snapshot, not a realtime delivery guarantee.

Text-only: <=10000characters/65535bytes, no empty/NUL/attachments. No API chat email/notifications, WebSocket, push, typing, edit/delete, group or reaction support. Polling strategy is not implemented by this phase; avoid aggressive polling. Global duplicate rooms remain possible from old website writer. Render participant text as plain text; no account email/phone lookup is provided.

## Review usage

GET received/given scoped to caller; newest review ID first. POST accepts post_ads_id,rating0..5,required review. Target is listing owner, reviewer authenticated. Self-review rejected, duplicate reviewer/listing409. No chat/acceptance/completion requirement, no approval/edit/delete/details endpoint, no email/notification.

The0 rating preserves unselected web form behavior. No new review-text business maximum exists; existing server HTTP limits still apply. **Current POST can create reviews for guessed pending listing IDs and return title/owner metadata**; resolve R03 policy/security finding before release. Do not build a client workaround that assumes backend filtering. Concurrent legacy website duplicates cannot be globally prevented with current constraints.

## Pagination and errors

Paginated listing, owner listing, conversation, message and review collections use:

```json
{"success":true,"message":"...","data":{"items":[],"pagination":{"current_page":1,"per_page":20,"total":0,"last_page":1}}}
```

Default20/max50. Reference APIs have items only. Offset pages are not immutable under concurrent inserts. Preserve deterministic server order; do not infer order from human-readable date_time/message_on alone.

- 401: invalid/expired token or invalid login; clear stale credential when authoritative.
- 403: account/token eligibility; show restricted state, no privilege escalation.
- 404: missing/private/non-owned resource; no existence probing.
- 409: known duplicate review or blocked listing deletion; handle per endpoint.
- 422: display field errors; correct requests rather than blind retries.
- 429: honor Retry-After, back off; no identity/IP rotation.
- 500: generic failure; do not display internal detail. POST send is not generally idempotent; blind retry can duplicate a message. Check history/reconcile before retrying.

## Phone/contact policy and unsupported features

Only own authorized profile phone is intentionally returned. Login, chat, listing ownership or review does not prove an accepted relationship. No accepted-request state exists, so no another-account phone-reveal feature exists. Free text/images may contain user-volunteered or obfuscated contacts; absolute content-level confidentiality is not established and requires a separate policy. Never derive phone access from UI flags or stored chat/review IDs.

Request acceptance, listing deletion/archive, chat attachments/push and review editing/moderation are NOT SUPPORTED. They cannot be promised merely because table IDs are present.

## Integration checklist — guidance only

Before real mobile integration: obtain separate approvals for missing token-table deployment and reported fixes; provide reachable HTTPS/API/media/email origins; complete CAPTCHA flow; verify actual mail delivery; set production environment/debug safely; resolve shared web vulnerabilities and privacy decisions; benchmark large references/chat/review behavior; review current dependency advisories; run physical-device tests. This phase did none of these operational actions. See final audit readiness matrix for IMPLEMENTED/TESTED/BLOCKED/NOT VERIFIED distinctions.

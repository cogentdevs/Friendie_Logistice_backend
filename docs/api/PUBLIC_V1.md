# Friendie Logistics (App) — API Details

Updated: 13 November 2026. This consolidated guide covers the currently registered APIs through Phase 6. Screenshots are intentionally omitted. Examples use synthetic values and are not executable credentials.

**Inventory: 28 routes — 27 implemented operations and one intentionally blocked listing DELETE (409).** Registration/token operations have deployment prerequisites; implemented does not mean enabled against the original database.

## Base URL and Postman setup

Use a Postman environment variable `base_url`, for example `http://127.0.0.1:8000/api/v1` when the local server is running on that port. All URLs below use `{{base_url}}`. Production requires the configured public HTTPS origin. A phone's localhost is not the development computer; Android emulator commonly uses `10.0.2.2`, while physical devices need a reachable configured hostname/LAN address. No address is hardcoded by this document.

Headers: `Accept: application/json`; JSON submissions also use `Content-Type: application/json`. Protected APIs require `Authorization: Bearer {{access_token}}`. For file uploads select Body → form-data and a File field; let Postman generate Content-Type and the multipart boundary. GET requests have no body.

Protected APIs require an active, verified, exclusively customer-role account and a valid Sanctum mobile token. Do not run mutating Postman requests against real accounts or the original database as a documentation check.

Success envelope: `{"success":true,"message":"...","data":{}}`. Validation: HTTP 422 with `{"success":false,"message":"Validation failed","errors":{"field":["..."]}}`. Other common errors: 401 unauthenticated, 403 ineligible/forbidden, 404 missing/non-owned resource, 409 blocked listing action, 429 rate limited, sanitized 500 server error.

## Public APIs — 8 endpoints

### 1. API Health

**URL:** `{{base_url}}/health`

**Method:** GET

**Authentication:** None

**Parameters:** None

**Response:** 200

```json
{"success":true,"message":"API is running","data":{"status":"ok","api_version":"v1"}}
```

### 2. Home API

**URL:** `{{base_url}}/home`

**Method:** GET

**Authentication:** None

**Parameters:** None

**Response:** 200; `data.categories`, `data.featured_listings`, `data.latest_listings`. Empty sections are arrays. Category collection is bounded to 12; each listing section to 8. Public eligibility and private-contact filtering apply.

### 3. Categories API

**URL:** `{{base_url}}/categories?search=par`

**Method:** GET

**Authentication:** None

**Optional query:** `search`

**Response:** 200; all eligible matches in `data.items`, no pagination. Item fields: `id`, `name`, `image_url`.

### 4. Countries API

**URL:** `{{base_url}}/countries?search=pak`

**Method:** GET

**Authentication:** None

**Optional query:** `search`

**Response:** 200; all eligible matches in `data.items`, no pagination. Exact item fields/examples appear in the public contract below.

### 5. Cities API

**URL:** `{{base_url}}/cities?country_id=1&search=kar`

**Method:** GET

**Authentication:** None

**Optional query:** `country_id`, `search`

**Response:** 200; all eligible matches in `data.items`, no pagination. Item fields: `id`, `country_id`, `name`, `short_name`. Without country_id, all eligible matching cities are returned.

### 6. Currencies API

**URL:** `{{base_url}}/currencies?search=usd`

**Method:** GET

**Authentication:** None

**Optional query:** `search`

**Response:** 200; normalized deduplicated currencies from existing country data, in `data.items`; no exchange rates or pagination.

For these four reference APIs, search is trimmed, case-insensitive literal LIKE substring matching. Exact matches rank first, then prefix, then other substring matches; alphabetical/stable ordering breaks ties. `%`, `_` and the escape character are literal input. `page`, `per_page` and `all` are not supported.

### 7. Public Listings API

**URL:** `{{base_url}}/listings?ad_type=Carring&page=1&per_page=20`

**Method:** GET

**Authentication:** None

**Optional query:** `search`, `category_id`, `from_country_id`, `to_country_id`, `from_city_id`, `to_city_id`, `ad_type`, `weight`, `departure_on`, `sort`, `page`, `per_page`.

**Sort:** `latest`, `oldest`, `departure`. Pagination defaults to 20, maximum 50. Generic `country_id` and `city_id` are not supported.

**Response:** 200; `data.items` and `data.pagination`. Only public eligible listings; no owner phone/email.

### 8. Public Listing Details API

**URL:** `{{base_url}}/listings/1`

**Method:** GET

**Authentication:** None

**Parameters:** Listing ID in path only.

**Response:** 200 public listing details, or 404 for missing/ineligible listing. No owner phone/email or private area fields.

## Authentication APIs — 7 endpoints

Full validation, rates, response JSON and mail policies: [AUTH_V1.md](AUTH_V1.md).

### 9. User Registration API

**URL:** `{{base_url}}/auth/register`

**Method:** POST

**Authentication:** None

**Body / raw JSON:** All following fields required.

```json
{"name":"Example Customer","email":"customer@example.test","phone":"+10000000001","country_id":1,"password":"ExamplePassword123!","password_confirmation":"ExamplePassword123!","captcha_token":"<fresh-valid-challenge-response>"}
```

**Response:** 201 neutral registration confirmation; no token. Creates customer with isActive=1, isVerified=0 when registration is available. Browser email verification is required before login. Duplicate email/phone receive neutral confirmation. CAPTCHA integration is required; four-field registration alone is insufficient.

### 10. User Login API

**URL:** `{{base_url}}/auth/login`

**Method:** POST

**Authentication:** None

**Body / raw JSON:** `email`, `password`.

```json
{"email":"customer@example.test","password":"ExamplePassword123!"}
```

**Response:** 200 with `data.token`, `data.token_type` and `data.user` (`id`, `name`, `email`). Invalid credentials or ineligible accounts receive neutral 401. Save token privately; never publish screenshots containing a live token.

### 11. Forgot Password API

**URL:** `{{base_url}}/auth/forgot-password`

**Method:** POST

**Authentication:** None

**Body / raw JSON:** `{"email":"customer@example.test"}`

**Response:** 200 neutral confirmation. Eligible account receives a browser reset link; no proof is returned by API. Shared reset expiry is 60 minutes.

### 12. Reset Password API

**URL:** `{{base_url}}/auth/reset-password`

**Method:** POST

**Authentication:** Emailed reset proof; no bearer token required.

**Body / raw JSON:**

```json
{"user_id":1,"token":"<64-lowercase-hex-reset-proof-from-email>","password":"NewExamplePassword123!","password_confirmation":"NewExamplePassword123!"}
```

**Response:** 200 on valid atomic reset; invalid/expired/replayed proof returns 422. Account ID alone never authorizes reset. No automatic login/token. Browser reset remains the default supported mobile recovery experience.

### 13. Resend Verification API

**URL:** `{{base_url}}/auth/resend-verification`

**Method:** POST

**Authentication:** None

**Body / raw JSON:** `{"email":"customer@example.test"}`

**Response:** 200 neutral confirmation. Only eligible active unverified customers receive mail. Rate limits and 60-second cooldown apply. Signed browser activation expires after exactly 24 hours; resend does not revoke older stateless links. Verification never enables disabled accounts or issues tokens.

### 14. Current Account API

**URL:** `{{base_url}}/auth/me`

**Method:** GET

**Authentication:** Bearer token

**Parameters:** None

**Response:** 200; own `id`, `name`, `email` in `data`.

### 15. Logout API

**URL:** `{{base_url}}/auth/logout`

**Method:** POST

**Authentication:** Bearer token

**Body:** Empty

**Response:** 200; revokes current token only. Other devices and website sessions are preserved.

## Customer Dashboard and Profile APIs — 6 endpoints

All require Bearer authentication. Full JSON contracts and policies: [CUSTOMER_PROFILE_V1.md](CUSTOMER_PROFILE_V1.md).

### 16. Customer Dashboard API

**URL:** `{{base_url}}/customer/dashboard`

**Method:** GET

**Parameters:** None

**Response:** 200; `data.listings` contains `total`, `published`, `pending`, `expired`; also `unread_messages` and `reviews_received`. Counts belong to current customer. Stored statuses are not public-visibility counts. No request/acceptance counts are invented.

### 17. Customer Profile API

**URL:** `{{base_url}}/customer/profile`

**Method:** GET

**Parameters:** None

**Response:** 200; own `id`, `name`, `email`, `phone`, `country_id`, `country`, `image_url`, `is_verified`, `email_verified_at`. No passwords or unrelated users' contact details.

### 18. Update Customer Profile API

**URL:** `{{base_url}}/customer/profile`

**Method:** PUT

**Body / raw JSON:** Supported fields `name`, `email`, `phone`, `country_id`; send only changes.

```json
{"name":"Updated Customer","phone":"+10000000003","country_id":1}
```

**Response:** 200 updated profile. Changed normalized email requires re-verification and reauthentication; previous mobile tokens cannot bypass verification. Role/status/password inputs are rejected. See detailed contract for email-change response flags.

### 19. Upload / Replace Avatar API

**URL:** `{{base_url}}/customer/profile/avatar`

**Method:** POST

**Body / form-data:** `image` (File). JPEG/PNG/WebP, maximum 2 MiB, dimensions at most 2048 x 2048.

**Response:** 200 with `data.image_url`. Server safely re-encodes the image; previous/default files are retained.

### 20. Remove Avatar API

**URL:** `{{base_url}}/customer/profile/avatar`

**Method:** DELETE

**Body:** Empty

**Response:** 200 with default `data.image_url` or null. Removes own reference, not physical old files.

### 21. Change Password API

**URL:** `{{base_url}}/customer/change-password`

**Method:** POST

**Body / raw JSON:**

```json
{"current_password":"CurrentExamplePassword123!","password":"NewExamplePassword123!","password_confirmation":"NewExamplePassword123!"}
```

**Response:** 200 with `data.reauthentication_required: true`. All this customer's mobile tokens are revoked; explicit login is required again. Existing unrelated website sessions are preserved. Invalid current password/policy/confirmation returns 422.

## Customer Listing APIs — 7 routes, 6 working operations

All require Bearer authentication and enforce current ownership. Full field rules, owner JSON, rate limits and Postman examples: [CUSTOMER_LISTINGS_V1.md](CUSTOMER_LISTINGS_V1.md).

### 22. My Listings API

**URL:** `{{base_url}}/customer/listings?status=0&ad_type=Carring&page=1&per_page=20`

**Method:** GET

**Optional query:** `search`, `status`, `ad_type`, `sort`, `page`, `per_page`. Status 0/1/2; types Carring/Send; sorts latest/oldest/departure. Pagination 20 default, 50 maximum.

**Response:** 200; own records in `data.items` with `data.pagination`, including pending/expired listings.

### 23. Create Listing API

**URL:** `{{base_url}}/customer/listings`

**Method:** POST

**Body / raw JSON:**

```json
{"ad_type":"Carring","title":"Parcel capacity","description":"Room for a parcel.","category_id":1,"from_country_id":1,"from_city_id":1,"to_country_id":2,"to_city_id":2,"weight":5,"weight_price":20.5,"currency":"USD","departure_on":"2030-11-20","reached_on":"2030-11-21"}
```

Required: `ad_type`, `title`, `category_id`, both country IDs, both city IDs, `currency`, `departure_on`, `reached_on`. Optional: `description`, `from_area`, `to_area`, `weight`, `weight_price`, `show_contact_info` (0 only). Same verified fields for Send. Reference IDs must be active, cities must belong to countries, arrival must not precede departure. Owner/status/featured are server controlled.

**Response:** 201 owner listing object; new status is pending (0), not publicly visible.

### 24. My Listing Details API

**URL:** `{{base_url}}/customer/listings/1`

**Method:** GET

**Parameters:** Owned listing ID in path.

**Response:** 200 owner-editable listing object, including pending/expired records, `status` and `image_url`. Missing/non-owned ID returns 404.

### 25. Update My Listing API

**URL:** `{{base_url}}/customer/listings/1`

**Method:** PUT

**Body / raw JSON:** Any supported editable subset, for example `{"title":"Updated parcel capacity","weight":7}`.

**Response:** 200 owner listing object. Existing status is preserved. Ownership/status/featured changes are rejected; expired listings are not automatically republished.

### 26. Delete Listing API — BLOCKED

**URL:** `{{base_url}}/customer/listings/1`

**Method:** DELETE

**Body:** Empty

**Response:** Owned listing returns **409**, not deletion success:

```json
{"success":false,"message":"Listing deletion is unavailable until a safe retention policy is approved."}
```

Missing/non-owned returns 404. No listing, relationships or images are deleted. Archive/soft-delete plan has been canceled; no archive field, migration or new status is authorized. Preserve 0=pending, 1=published, 2=expired. This route remains blocked until an existing safe deletion workflow can be reused without schema or website changes.

### 27. Upload / Replace Listing Image API

**URL:** `{{base_url}}/customer/listings/1/image`

**Method:** POST

**Body / form-data:** `image` (File). JPEG/PNG/WebP, maximum 2 MiB, dimensions at most 2048 x 2048.

**Response:** 200 with `data.image_url`. Single-image schema only; safe generated filename and JPEG re-encoding. Old/default files remain intact.

### 28. Remove Listing Image API

**URL:** `{{base_url}}/customer/listings/1/image`

**Method:** DELETE

**Body:** Empty

**Response:** 200 with default `data.image_url` or null. Resets own reference to `listing.png`; no physical image deletion or listing-status change.

## Current integration prerequisites and scope

- Original database Sanctum token-table migration still needs separate approval before original-database token login can work. Do not run migrations from this guide.
- Configure device-reachable trusted API/media and browser email-link origins. `API_PUBLIC_BASE_URL` controls public media as detailed below; localhost may not be reachable from Android.
- Mobile registration requires a valid CAPTCHA integration; email verification remains browser-based.
- Existing Blade composer side effects and legacy website security concerns remain separately documented release risks.
- No request/acceptance, phone-reveal, chat or review REST APIs have been implemented. Web chat/review features are not mobile API endpoints.
- Another user's phone/email is never authorized merely by login. Own profile contact data is intentionally owner-only.
- No screenshots, credentials, live tokens, app code or database changes are included in this documentation update.

---

# Detailed Phase 3 public API contract

All routes below are guest GET routes under `/api/v1`; Laravel also registers HEAD.
No cookies, bearer tokens or `auth:sanctum` middleware are required. The existing health
route is retained. No other business route or database migration is added.

## Sources and eligibility

Verified against the actual local MariaDB schema, not historical migrations:

| Data | Actual table/columns used |
| --- | --- |
| Categories | `categories.id,name,image,isActive` |
| Countries | `countries.id,name,short_name,image,isActive,currency_name` |
| Cities | `cities.id,country_id,name,short_name,isActive`; joined country visibility |
| Listings | `post_ads.id,category_id,ad_type,title,description,image,weight,weight_price,currency,from_country_id,from_city_id,to_country_id,to_city_id,departure_on,reached_on,isFeatured,isActive,created_by,created_at` |
| Owner | `users.id,name,image`, joined through `post_ads.created_by` |

Models currently have no declared relationships/scopes to reuse. Read-only query-builder
joins avoid changing models or invoking existing web controller/view behavior. No `SELECT *`
is used on listings or users. Reference lists select only their explicit public columns;
currencies use an explicitly projected derived table.

Listings require `post_ads.isActive = 1`, an active category, and non-null departure on or
after today's date in the configured application timezone (currently UTC). The existing
admin publish handler sets 1; pending is 0; the existing Blade composer marks past dates 2.
Missing owner/category/country/city joins exclude a record. Detail uses exactly the same
eligibility query and returns the same 404 for missing and ineligible records.

Today's departure is not expired: the composer compares `< today`. The existing featured
home section uses the stricter `> today`, which is preserved for featured previews only.
All status-2 listings are excluded, even if their date later changes. No status, timestamp,
view counter or other record is updated by these endpoints. The website's inconsistent
`1 OR 2` queries and mutating composer remain unchanged.

Country/city IDs retain their stored directional meaning. Existing inconsistent city/country
pairs are not silently repaired or excluded by an invented relationship constraint. Listings
retain the website's joins; reference cities additionally require an active parent country.
No new owner-verification or geography-publication rules are invented.

## Responses and parameters

Every success uses `success: true`, an endpoint message and `data`. The four reference APIs
return `data.items` only, containing all eligible matching records with no pagination or
silent limits. Empty items is `[]`. Listings retain `data.items` and `data.pagination`
(`current_page`, `per_page`, `total`, `last_page`); last page is 1 when empty. No
client-host-derived pagination URLs are emitted.

All parameters are optional. Unknown parameters, malformed values, arrays where strings are
expected and invalid bounds return 422. IDs are positive integers <= 2147483647; page is
1..100000. A syntactically valid nonexistent filter ID returns empty results, not a private
existence lookup. Only listings accept pagination, defaulting to 20 per page, maximum 50.
Reference APIs reject `page`, `per_page`, `all` and other unsupported parameters with 422.
Reference sorting is name then ID, currencies code; search adds relevance ranking described
below. Listings default ID descending. There is no parent-category relationship or invented
status/currency table.

| Endpoint | Parameters | Data and limits |
| --- | --- | --- |
| `/home` | none | categories <=12 (name/ID order), featured <=8 and latest <=8 (ID descending); three bounded joined queries |
| `/categories` | `search` | all active matching categories, ID/name/image only; no misleading all-status listing counts |
| `/countries` | `search` | all active matching countries, ID/name/short name/image; null/blank name falls back to short name |
| `/cities` | `country_id,search` | all active matching cities in active countries; optional exact country; no country filter returns all eligible cities |
| `/currencies` | `search` | all matching distinct nonblank `UPPER(TRIM(countries.currency_name))` from active countries; no exchange rates or inferred currency table |
| `/listings` | `search,category_id,from_country_id,to_country_id,from_city_id,to_city_id,ad_type,weight,departure_on,sort,page,per_page` | eligible public cards |
| `/listings/{id}` | none | eligible public card plus plain-text `description` |

### Reference search and complete results

`search` is optional on all four reference APIs, trimmed before validation, maximum 100
characters. Omitted, empty or whitespace-only search returns all eligible records. Arrays
and overlong values return 422. Countries/categories/cities search their database `name`;
currencies search the existing normalized deduplicated `code`. Country short-name fallback
is retained for display, not added as a separate search field.

Matching is case-insensitive SQL LIKE substring matching, using `LOWER()` and bound values
with the configured MariaDB UTF-8 collation. `%`, `_` and the explicit escape character `!`
are escaped so input is literal text; backslash is also literal because `!` is the SQL
escape. No request input is concatenated into SQL identifiers or ranking expressions.

Search ranks: (1) exact match, (2) prefix match, (3) all other substring matches. Within a
rank, sort alphabetically then ID; normalized distinct currency codes are their own stable
key. Without search, use alphabetical order then ID (currency code alone). For `search=kar`,
`Kar` precedes `Karachi`, which precedes `Shikarpur`. Matching/ranking use the database's
existing collation semantics, including its accent equivalence. City search and country
filter are ANDed; neither bypasses active city/parent-country restrictions.

Examples: `/countries?search=pak`, `/categories?search=par`, `/currencies?search=usd`,
`/cities`, `/cities?country_id=1`, `/cities?search=kar`,
`/cities?country_id=1&search=kar`. No separate dropdown/options endpoints exist.

Read-only inspection before this change found 252 eligible countries, 9,009 eligible cities,
1 eligible category and 155 distinct eligible currencies. These are observed dataset counts,
not limits. An unfiltered city request now intentionally returns the complete eligible
dataset. Memory, JSON response size and mobile parsing/rendering costs grow with it; LIKE
substring scans/LOWER and relevance sorting can be expensive at worldwide scale. One data
query per endpoint replaces pagination/count queries, but this is not a load-test result.
Use optional search/country filters and client-side caching/debouncing where appropriate;
these remain optional and never authorize truncation. No index, migration, server cache,
or database record is changed. Listing and home limits/contracts remain unchanged.

The updated contract adds 22 focused reference tests, including more than 100 returned
records for each API, no pagination/limits, all three ranking tiers, literal wildcard and
injection handling, parent-country visibility and SELECT-only SQL. Combined selected
foundation/public/authentication/customer regressions passed 218 tests / 1,937 assertions,
with one existing PHP 8.4 vendor deprecation. Physical-device performance and worldwide
production load were not benchmarked.

Listing filters are ANDed. `search` is a 1..100-character literal substring across title OR
category name, enclosed in one WHERE group. `%`, `_`, `!` are escaped; values are bound.
Private area/description text is not searched. `ad_type` accepts the existing stored values
`Carring` (legacy spelling) or `Send`. Weight is an exact nonnegative integer <=2147483647;
departure is an exact `Y-m-d`. Sort accepts only `latest`, `oldest` or `departure`; departure
sort is date ascending then ID descending, so ties are stable. Generic `country_id`/`city_id`
on listings are intentionally unsupported because origin/destination would be ambiguous.
No area ID/filter is added: actual `from_area` and `to_area` are VARCHAR and may hold private
addresses. No price comparison across currencies or invented monetary units are added;
`weight_price` preserves the existing field name and value.

## Example JSON (illustrative synthetic data)

`GET /api/v1/home`:
```json
{"success":true,"message":"Home retrieved successfully.","data":{"categories":[{"id":1,"name":"Parcels","image_url":null}],"featured_listings":[],"latest_listings":[]}}
```

`GET /api/v1/categories`:
```json
{"success":true,"message":"Categories retrieved successfully.","data":{"items":[{"id":1,"name":"Parcels","image_url":null}]}}
```

`GET /api/v1/countries`:
```json
{"success":true,"message":"Countries retrieved successfully.","data":{"items":[{"id":1,"name":"Alpha","short_name":"AA","image_url":null}]}}
```

`GET /api/v1/cities?country_id=1`:
```json
{"success":true,"message":"Cities retrieved successfully.","data":{"items":[{"id":1,"country_id":1,"name":"Alpha City","short_name":null}]}}
```

`GET /api/v1/currencies`:
```json
{"success":true,"message":"Currencies retrieved successfully.","data":{"items":[{"code":"USD"}]}}
```

`GET /api/v1/listings`:
```json
{"success":true,"message":"Listings retrieved successfully.","data":{"items":[{"id":1,"ad_type":"Carring","title":"Carry a parcel","short_description":"Room for a small parcel.","image_url":null,"category":{"id":1,"name":"Parcels"},"origin":{"country_id":1,"country":"Alpha","city_id":1,"city":"Alpha City"},"destination":{"country_id":2,"country":"Beta","city_id":2,"city":"Beta City"},"weight":5,"weight_price":20,"currency":"USD","departure_on":"2026-11-13","reached_on":"2026-11-14","is_featured":true,"created_at":"2026-11-01 10:00:00","owner":{"id":1,"display_name":"Public Owner","image_url":null}}],"pagination":{"current_page":1,"per_page":20,"total":1,"last_page":1}}}
```

`GET /api/v1/listings/1`:
```json
{"success":true,"message":"Listing retrieved successfully.","data":{"id":1,"ad_type":"Carring","title":"Carry a parcel","short_description":"Room for a small parcel.","image_url":null,"category":{"id":1,"name":"Parcels"},"origin":{"country_id":1,"country":"Alpha","city_id":1,"city":"Alpha City"},"destination":{"country_id":2,"country":"Beta","city_id":2,"city":"Beta City"},"weight":5,"weight_price":20,"currency":"USD","departure_on":"2026-11-13","reached_on":"2026-11-14","is_featured":true,"created_at":"2026-11-01 10:00:00","owner":{"id":1,"display_name":"Public Owner","image_url":null},"description":"Room for a small parcel."}}
```

Errors reuse the Phase 2 renderer: 404 `{"success":false,"message":"Resource not found"}`;
422 `{"success":false,"message":"Validation failed","errors":{"per_page":["..."]}}`;
405 for unsupported methods and generic 500 for server failures. No SQL/stack traces are sent.
These public routes do not generate 401; existing protected-route handling remains tested.

## Privacy and media

Explicit serialization excludes user email/phone/password/token/verification fields, private
area/address values, `show_contact_info`, internal status and administrative fields. Owner
data is only ID/display name/public avatar. The query does not even retrieve account secrets.
Listing summaries omit full description. HTML/script/style are removed; recognizable
contact-bearing text (email, telephone patterns, URLs/contact/address keywords) is withheld
as null, including in titles/display names. This is conservative legacy text handling, not a
guarantee of semantic content moderation: obfuscated contacts, addresses in arbitrary
languages or text embedded in uploaded images require a separately reviewed content policy.
No accepted relationship exists to authorize phone disclosure in this phase; login alone or
the legacy `show_contact_info` flag never enables it.

Plain descriptions are bounded to 10,000 characters (card excerpts 200). Public string
labels are bounded to their source-column lengths. Contact-bearing legacy text may therefore
produce null values; clients should handle these without attempting to reveal original text.

Images are a single existing filename per record, not an invented gallery. The serializer
allows local jpg/jpeg/png/gif/webp basenames only, rejects path traversal/remote/SVG input,
checks file existence and its resolved location inside `public`, URL-encodes the filename
and uses `api.public_base_url` (optional `API_PUBLIC_BASE_URL`), falling back to `app.url`
only when the API setting is absent/blank. Missing/unsafe images or invalid base URLs return
null. Listing/profile/category/flag directories remain unchanged. No placeholders are
fabricated. No domain, localhost or emulator address is hardcoded into application source.

## Public media configuration and mobile integration

`config/api.php` introduces only the API setting. Website `APP_URL`, `ASSET_URL`, Blade
asset helpers, CORS, CSRF and web controllers are unchanged. No `.env` file was edited.
Every public category/listing/profile/flag image uses the same serializer, including home
previews and details; response field names are unchanged.

The base must be the absolute HTTP/HTTPS **public application root**, including a port or
application subdirectory if needed. It must serve the actual `public` directory. Do not
append `/api/v1`, `/backend/img/category`, `/frontend/img`, or `/public` when `public` is
already the document root. The serializer appends the existing relative image path once,
normalizes repeated/trailing path slashes and URL-encodes filenames. Never configure a
filesystem path. Credentials, query strings, fragments, non-HTTP schemes and dot segments
are rejected; an explicitly invalid setting does not silently fall back to another host.
Request Host and forwarded headers are never the URL authority.

Choose **one** appropriate setting yourself; these are non-secret examples, not changes made
by this task:

| Client/environment | API request example | Matching media setting |
| --- | --- | --- |
| Windows browser/Postman | `http://127.0.0.1:8000/api/v1/categories` | `API_PUBLIC_BASE_URL=http://127.0.0.1:8000` |
| Standard Android Studio emulator | `http://10.0.2.2:8000/api/v1/categories` | `API_PUBLIC_BASE_URL=http://10.0.2.2:8000` |
| Physical Android device | `http://<COMPUTER_LAN_IP>:8000/api/v1/categories` | `API_PUBLIC_BASE_URL=http://<COMPUTER_LAN_IP>:8000` (replace placeholder with a reachable address or development hostname) |
| Production | `https://api.example.com/api/v1/categories` | `API_PUBLIC_BASE_URL=https://api.example.com` |

On a physical device, `localhost` and `127.0.0.1` refer to the device itself, not this
Windows computer. `10.0.2.2` is the standard Android emulator's host alias, not a general
physical-device address. A device-reachable LAN/development hostname can serve both
Postman and the device. The single configured origin must be reachable by intended clients;
it deliberately does not change based on incoming request headers.

A server bound only to loopback may require an owner-managed binding/firewall adjustment
before physical devices can connect. No server was restarted/rebound here. Native Android
cleartext policy, network/firewall access, actual HTTPS certificates and app API base URL
still need manual integration checks. No Android files or network/CORS policies were changed.
Existing deployments with cached configuration must incorporate this variable through their
normal approved configuration/release procedure; no cache-clear/build command was run here.

### Verified local image issue

On November 12, 2026:

- The original generated `http://localhost/backend/img/category/category-04.jpg` returned
  HTTP **404** from Apache on port 80.
- `http://127.0.0.1:8000/backend/img/category/category-04.jpg` returned **200**,
  `Content-Type: image/jpeg`, **27,684 bytes**. Its downloaded SHA-256 matched the existing
  `public/backend/img/category/category-04.jpg` exactly. This verifies the current port-8000
  public image mapping without invoking Blade or a write-capable application workflow.
- Server process/command-line inspection was access-restricted, so the launch command and
  Apache virtual-host configuration were not certified. The static HTTP/file comparison
  verifies the tested URL mapping; it does not establish a production document-root policy.
- With a valid configured API base, isolated tests confirm category/listing/profile/flag
  URLs use that base, preserve URL encoding, handle missing files and ignore hostile Host/
  forwarded headers. Tests also confirm web asset generation and `app.url` stay unchanged.

Example after manually choosing the Windows test setting:
`http://127.0.0.1:8000/backend/img/category/category-04.jpg`.
For a physical device, choose its reachable server hostname/IP instead; substituting
127.0.0.1 alone does not solve device connectivity. Until a valid override is configured,
the existing APP_URL fallback remains in effect, so the current localhost mismatch remains
an environment setup item. Physical Android/emulator reachability was **not tested** here.

### Manual Postman/device checks

1. Choose the matching API/media origins for the intended client, without changing web URLs
   unnecessarily. Request `/api/v1/health` and each of the seven public routes without auth.
2. Open the returned `image_url` directly: expect 200 and an image content type. Check that
   origin/port/subdirectory match the public server mapping and no `/public`/image prefix is
   duplicated. Null is expected for absent/unsafe files.
3. Repeat both the API request and returned image URL on the intended device. Backend
   feature tests cannot prove Wi-Fi routing, firewall, emulator or Android network policy.
4. Check populated listings with isolated synthetic fixtures; do not republish historical
   original-database records just to produce public results.

## Safety and validation

Use only the Phase 2A restricted isolated test database, actual-schema baseline and
`DatabaseTransactions`. Do not run migrations, use RefreshDatabase, seed the original
database or test through Blade rendering. The test bootstrap denies access to the original
database, isolates public/storage/cache paths, fakes mail/queue/notifications and blocks
outbound HTTP. Test-only synthetic uploads live in the temporary public directory.

```powershell
php vendor/bin/phpunit --configuration phpunit.xml --filter 'ApiFoundationTest|SafetyEnvironmentTest|PublicApiTest' --do-not-cache-result --display-deprecations
```

Tests cover all guest routes, explicit response keys/privacy canaries, query validation,
status/date boundaries, grouped search, directional filters, stable pagination, currencies,
media paths, bounded home queries and SELECT-only GET behavior. Foundation coverage retains
the exact 80-route web/admin fixture, session/CSRF settings, health and JSON 401/404/422/500.
Browser business workflows and Android/iOS network integration are not certified by these
tests. Original database/table fingerprints and protected source/uploads are checked
separately against the restricted Phase 2A manifests.

## Read-only investigation of empty original listing results

Verified on November 12, 2026 using SELECT-only inspection of `friendie_logistics`:

| Status | Count | Existing meaning |
| --- | --- | --- |
| 0 | 0 | pending; the admin pending query filters 0 |
| 1 | 0 | published; the admin publish handler sets 1 |
| 2 | 15 | used for expired records by the Blade composer |

All **15** departures are past: earliest **2024-10-26**, latest **2025-01-25**. None are
undated. The configured application timezone is **UTC**, and the inspected application date
was **2026-11-12**. A timezone boundary cannot make these historical records unexpired.
No missing/inactive categories, missing required joins or inactive referenced locations
were found among these records. They are excluded by publication/expiry, not broken joins.

- `PostAdsController.php:150-172`: creation does not assign `isActive`, so the actual
  database default **0** applies; historical migrations may differ.
- `PostAdsController.php:110-117`: admin publication sets **1**.
- `PostAdsController.php:244`: existing edit logic accepts a submitted status; no immutable
  status-transition audit exists, so status 2 alone is not proof of every record's history.
- `AppServiceProvider.php:28-39`: a wildcard Blade composer updates past-departure ads to
  **2**, comparing `departure_on < date('Y-m-d')`, regardless of prior status. It was not
  invoked by this investigation.
- `FrontController.php:40-41`: featured website data requires published status and future
  departure. Other website browsing queries (`:59-61`, `:166-168`) include **1 OR 2**, even
  expired records. Website details also lack the API's eligibility guard.

The API intentionally does not copy the website's expired-record exposure. It preserves
the approved published/nonexpired contract and adds no status-changing behavior. All 15
current records are genuinely historical by date. API filtering was **not changed** in this
closure task. Fresh content/publication or changes to website business logic require
separate approval; no data was reactivated, extended, republished, deleted or repaired.

## Closure verification and remaining work

The final isolated suite passed **65 tests / 422 assertions**, including all seven guest
endpoints, health, JSON 401/404/422 handling, exact 80-route web/admin inventory, session
and CSRF configuration, publication/expiry, filters, pagination, privacy and media settings.
One existing PHP 8.4 deprecation remains in `voku/portable-ascii`; no test failed.

Existing reset/activation vulnerabilities, web phone disclosure, historical schema drift,
city/country inconsistencies and dependency advisories remain outside this phase. No real
records are republished or repaired. Future authentication and
accepted-request work need separate security approval and original-database migration
approval. No automatic Phase 4, deployment or production-readiness claim is made.

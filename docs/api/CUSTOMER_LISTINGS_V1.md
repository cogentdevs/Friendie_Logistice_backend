# Customer listing management API v1 — Phase 6

**Status: PARTIAL.** Owner browse/create/detail/update and single-image management are implemented. Destructive DELETE is deliberately blocked with HTTP 409 pending an approved retention/dependency policy. Seven routes exist; a registered deletion route must not be described as completed destructive deletion. No Phase 7 workflow is implemented.

## Scope update — archive plan canceled

The owner canceled the proposed archive/soft-delete implementation after the read-only Phase 6B audit. No `archived_at`, `deleted_at`, archive flag, new listing status or migration is authorized or implemented. DELETE remains HTTP 409 until an existing safe deletion behavior can be reused without database/schema or website/admin workflow changes. Six working Phase 6 operations and their tests remain intact. References below to a retention policy describe the blocked route's current implementation, not approval to add archival.

For the consolidated URL / Method / Body guide covering all 28 registered API routes, see [PUBLIC_V1.md](PUBLIC_V1.md). Detailed authentication and profile contracts remain in [AUTH_V1.md](AUTH_V1.md) and [CUSTOMER_PROFILE_V1.md](CUSTOMER_PROFILE_V1.md).

## Verified existing lifecycle

Inspected `routes/web.php`, `PostAdsController`, customer add/edit/my-listing Blade sources, `PostAd`, the actual MariaDB schema, public listing query and customer dashboard. No Blade page was rendered against the original database.

- `post_ads.ad_type` is VARCHAR; both stored values and form options are exactly `Carring` and `Send`.
- New website inserts omit `isActive`; the actual database default is **0/pending**, unlike the old migration default of 1. Admin `markPublishedPostAds()` sets **1/published**.
- The wildcard view composer sets past-departure listings to **2/time out/expired**, regardless of prior status. It remains unchanged and is never invoked by these APIs.
- Both type options use the same form requirements. There is no verified type-specific field difference. Weight/price and areas are optional; route/category/title/dates/currency are required in the form.
- Owner management lists are not limited by public expiry/publication eligibility. The UI offers edit links across statuses, and the shared edit controller contains no status-based field edit restriction, dependency restriction or automatic renewed-approval transition. It unsafely accepts a submitted status. The API instead preserves the stored status and forbids client status/featured/owner changes. It does not invent a renewed-approval policy.
- Editing a published listing keeps it published; editing an expired listing's date does not republish it. Admin workflow remains responsible for publication. Unknown stored statuses are readable by their owner but mutating edit/image operations return 409.
- There is one nullable `image` filename column; default `listing.png`; existing public directory is `frontend/img/listing`. No galleries, soft-delete column, declared model relationships or safe media-deletion ownership policy exist.
- Existing web delete calls hard delete without owner/dependency checks; nondefault-image deletion also references an undefined variable/missing facade import. Rooms and reviews reference listing IDs, messages reference rooms, and the actual schema has no foreign keys to `post_ads`. Legacy writers do not coordinate on the parent row. A dependency check plus parent lock alone cannot guarantee concurrency-safe deletion across the existing website.

## Authentication and common behavior

Base path `/api/v1/customer/listings`. All seven routes use `auth:sanctum` and `EligibleMobileCustomer`. Only active, verified, exclusively customer-role accounts with a real bearer token and mobile account ability qualify. No website session fallback.

```text
Accept: application/json
Authorization: Bearer <access-token>
Content-Type: application/json
```

Use HTTPS in production. For multipart uploads let the client set its multipart boundary instead of setting JSON Content-Type. No token, account ID or owner ID in the body selects an owner. Every lookup is scoped by the authenticated `created_by`; missing/non-owned records return the same 404. Mutation transactions recheck account eligibility/token existence and lock the owned listing. Read GETs reuse Phase 5 read-only Sanctum authentication, avoiding token last-used writes as well as listing/view/timestamp writes.

Standard envelopes: success has `success,message,data`; errors have `success:false,message` and optional validation `errors`. Unknown or privileged input is rejected, not mass assigned. No user table join or owner contact information is serialized. Own editable descriptions/areas are returned only to the authenticated owner; legacy HTML remains untrusted text and must not be rendered as trusted HTML. Public serialization remains unchanged.

## GET /api/v1/customer/listings

Optional query parameters:

| Parameter | Validation / behavior |
| --- | --- |
| `search` | Title-only, 1–100 characters, case-insensitive literal substring; bound SQL with `%`, `_`, `!` escaped |
| `status` | Integer 0, 1 or 2; stored status, not computed expiry |
| `ad_type` | Exactly `Carring` or `Send` |
| `sort` | `latest` (ID descending, default), `oldest` (ID ascending), `departure` (date ascending then ID descending) |
| `page` | Positive integer, max 100000; default 1 |
| `per_page` | 1–50; default 20 |

Filters are ANDed with authenticated ownership. Without status filtering all own records are returned, including pending/expired and legacy records whose geography is missing or inactive. No public-listing joins hide an owner's record. `all`, unknown filters, malformed values and unsupported sorts return 422. Stable ordering and existing `data.pagination` metadata are retained.

```json
{"success":true,"message":"Your listings retrieved successfully.","data":{"items":[],"pagination":{"current_page":1,"per_page":20,"total":0,"last_page":1}}}
```

## POST /api/v1/customer/listings

JSON body, same fields for both verified listing types:

```json
{
  "ad_type":"Carring",
  "title":"Parcel capacity",
  "description":"Room for a parcel.",
  "category_id":1,
  "from_country_id":1,
  "from_city_id":1,
  "from_area":"Example origin area",
  "to_country_id":2,
  "to_city_id":2,
  "to_area":"Example destination area",
  "weight":5,
  "weight_price":20.5,
  "currency":"USD",
  "departure_on":"2030-11-20",
  "reached_on":"2030-11-21"
}
```

The same request with `ad_type: "Send"` is supported; no invented type-specific requirements.

| Field | Rule |
| --- | --- |
| `ad_type` | Required, exact existing value |
| `title` | Required string, max 255 characters |
| `description` | Optional/nullable string, max 10000 characters |
| `category_id` | Required active existing category |
| Origin/destination country IDs | Required positive IDs for active countries |
| Origin/destination city IDs | Required active cities belonging to the corresponding countries |
| `from_area`, `to_area` | Optional/nullable VARCHAR text, max 255; never treated as foreign keys |
| `weight` | Optional/nullable integer, 0–2147483647 |
| `weight_price` | Optional/nullable finite nonnegative numeric value; no currency conversion or fabricated price cap |
| `currency` | Required string, max 11 characters (actual listing column); normalized uppercase/trimmed and must match normalized currency of an active country |
| `departure_on`, `reached_on` | Required actual dates `Y-m-d`; reached date must be same or later than departure |
| `show_contact_info` | Optional integer **0 only**; defaults to 0. No acceptance exists to authorize enabling the legacy public-contact flag |

No future-only departure restriction was found in the verified form/controller, so past dates are not arbitrarily rejected. They never bypass public date filtering; no status synchronization occurs here. A business decision to prohibit historical submissions needs separate approval. Numeric/date/reference rules and text bounds are explicit API validation hardening; the old web controller lacks equivalent robust server validation.

Server assigns authenticated owner, `isActive=0`, `isFeatured=0`, default image and timestamps. Reject `user_id`, `owner_id`, `created_by`, `status`, `isActive`, `isVerified`, `isFeatured`, approval/system timestamps, raw image filenames and other unknown fields. Files go through the separate image endpoint.

HTTP **201**: `Listing created and pending approval.` with the owner listing object below. A future-dated pending record remains absent from public home/listings/details until the existing admin/public rules permit it. No notification/email is sent.

## GET /api/v1/customer/listings/{id}

Positive canonical numeric ID; no query parameters. Returns owned pending/published/expired details, regardless of public eligibility. HTTP 200 message `Your listing retrieved successfully.`

Owner object (also used for list items and create/update responses):

```json
{
  "id":1,
  "category_id":1,
  "from_country_id":1,
  "from_city_id":1,
  "to_country_id":2,
  "to_city_id":2,
  "weight":5,
  "ad_type":"Carring",
  "title":"Parcel capacity",
  "description":"Room for a parcel.",
  "currency":"USD",
  "from_area":"Example origin area",
  "to_area":"Example destination area",
  "departure_on":"2030-11-20",
  "reached_on":"2030-11-21",
  "created_at":"2030-11-01 10:00:00",
  "updated_at":"2030-11-01 10:00:00",
  "weight_price":20.5,
  "status":0,
  "is_featured":false,
  "show_contact_info":0,
  "image_url":null
}
```

Never includes `created_by`, raw image filename, user phone/email/password, unrelated accounts or chat/review content. Optional legacy columns can be NULL. Image URL is the trusted configured public URL or NULL for missing/unsafe files. Responses with owner details are private/no-store.

## PUT /api/v1/customer/listings/{id}

Partial JSON update; at least one editable field required. Only the creation field allowlist is accepted, with supplied fields validated. A changed country/city is checked against the effective pair including the unchanged value. A changed date is checked against the effective departure/reached pair. Untouched legacy reference fields are not silently repaired/reclassified. Updating a legacy row does not automatically enable `show_contact_info`; only explicit 0 is accepted for changes to that flag. An existing legacy value 1 is preserved if not submitted; the existing website's separate contact-display weakness remains a release issue, not permission for API phone disclosure.

```json
{"title":"Updated parcel capacity","weight":8}
```

Known statuses 0/1/2 permit field edits as in the existing unconditional edit workflow. Stored owner, status, featured flag, creation time, image and unrelated columns are preserved; `updated_at` reflects the edit. Dates cannot republish status 2. HTTP 200 message `Listing updated successfully.` and updated owner object. No new re-approval, request/acceptance or dependency-based edit policy is invented; those require a separate business decision if desired.

## DELETE /api/v1/customer/listings/{id} — BLOCKED

Owned record: HTTP **409**, no successful deletion:

```json
{"success":false,"message":"Listing deletion is unavailable until a safe retention policy is approved."}
```

Missing/non-owned: 404. No listing, chat, room, message, review, user or image is deleted, even for an apparently unreferenced listing. This intentionally does **not** reproduce unsafe web hard-deletion. A future approved solution must define retention and concurrent legacy-writer behavior: e.g. an additive archive/soft-delete design or a dependency-safe hard-delete design with database/writer coordination. No schema change or cascading deletion is authorized or implemented here. Existing web deletion remains unchanged.

## POST /api/v1/customer/listings/{id}/image

Postman Body → form-data → **`image`**, type File. One image only; no gallery. The customer must own the listing. Known status 0/1/2 remains unchanged.

- JPEG, PNG, WebP extension and actual detected/decodable content.
- Maximum 2048 KiB; input width/height 1–2048 pixels.
- Re-encoded JPEG, bounded to 420×340 without upscaling or distorting aspect ratio. The web's existing directory/default convention is retained; its unsafe original-extension/deletion logic is not reused.
- Filename `api_listing_<listing-id>_<128-bit-random>.jpg`, created exclusively under the resolved existing public listing directory. No request Host or client path chooses its authority/location.
- Database/file failure keeps the old reference and cleans up only the newly created request file. Old/default/shared files are never deleted. Process interruption can leave an unreferenced generated file; DB/filesystem crash atomicity is not claimed.

HTTP 200:

```json
{"success":true,"message":"Listing image updated successfully.","data":{"image_url":"https://media.example.test/frontend/img/listing/api_listing_1_<random>.jpg"}}
```

## DELETE /api/v1/customer/listings/{id}/image

Empty body. Resets only the owned listing's image reference to existing `listing.png`. Missing old media is safe. Preserves status/owner/other fields, updates the modification timestamp. HTTP 200 message `Listing image removed successfully.` with the default trusted `data.image_url` or NULL if absent. Physical files remain, so older public image URLs may continue to work; physical erasure/disk cleanup requires an approved ownership-aware policy.

## Limits and errors

Per authenticated account **and IP**: create 10/hour, update 30/15 minutes, attempted listing delete 10/15 minutes, combined image upload/removal 10/hour. Failures count. Limits return 429 plus Retry-After. GETs have bounded pagination without mutation rate-counter writes.

401 unauthenticated; 403 ineligible customer; 404 missing/non-owned listing; 422 invalid/unknown fields or query parameters; 409 blocked deletion or unsupported legacy mutation state; 429 throttled; sanitized 500 unexpected/storage/database failure. Unsupported-state 409 uses the existing generic renderer message `Request failed`. Validation example:

```json
{"success":false,"message":"Validation failed","errors":{"from_city_id":["Select an active city belonging to the selected country."]}}
```

## Postman, compatibility and readiness

1. Use a synthetic active verified account on the restricted isolated backend; obtain a bearer token from Phase 4 login.
2. Read existing reference APIs for active category/geography/currency. They still return complete `data.items`, no pagination, literal case-insensitive search with exact/prefix/substring ranking, and only cities accept `country_id`.
3. POST a JSON listing and save its returned ID. Expect pending 0 and public details 404; don't change real statuses merely to make it public.
4. GET owner list with `status=0&ad_type=Carring&per_page=20&page=1`, then GET details. PUT only editable fields; don't echo response status/system fields back.
5. POST multipart image; open its trusted URL on the intended client. DELETE image resets reference, while DELETE listing remains 409 pending policy approval.

Public home/listings/details eligibility, privacy fields and pagination are unchanged. Authentication does not authorize another user's phone; request acceptance is absent. No website/admin UI/controllers/routes, expiry composer, reference API contract, app files, dependencies or original records are changed. Original Sanctum migration, reachable API/media/email origins and existing production security issues remain separate prerequisites. No commit/push/deployment or Phase 7 work is included.

## Implementation verification

The isolated focused/regression run passed **284 tests and 2,495 assertions**, including 66 customer-listing tests. One existing PHP 8.4 deprecation in the vendor portable-ascii dependency remains; no assertion failed. PHP syntax checks passed for all six changed/new PHP files and `git diff --check` passed.

Read-only before/after checks matched all 25 original database table fingerprints. All 43 baseline media hashes matched. A separate checksum of 1,975 pre-existing files, including the Git index, environment files and prior uncommitted work, matched after excluding the two deliberately extended route/inventory files. These checks establish integrity for this task; they do not establish that the current cache table equals an older backup snapshot.

Phase 6 remains **PARTIAL**: six operations are functional; listing DELETE is deliberately fail-closed with HTTP 409 until retention and concurrency-safe deletion are separately approved. Changes remain unstaged/uncommitted; no original-database migration or expiry command was run.

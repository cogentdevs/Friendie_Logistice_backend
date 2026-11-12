# Phase 3 public API contract

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
is used on listings or users. Reference pagination selects only its explicit public columns;
currency pagination uses an explicitly projected derived table.

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

Every success uses `success: true`, an endpoint message and `data`. Paginated endpoints use
`data.items` and `data.pagination` (`current_page`, `per_page`, `total`, `last_page`). Empty
items is `[]`; last page is 1 when empty. No client-host-derived pagination URLs are emitted.

All parameters are optional. Unknown parameters, malformed values, arrays where strings are
expected and invalid bounds return 422. IDs are positive integers <= 2147483647; page is
1..100000. A syntactically valid nonexistent filter ID returns empty results, not a private
existence lookup. Reference endpoints default to 50 per page, maximum 100. Listings default
to 20, maximum 50. Reference sorting is name then ID, currencies code; listings default ID
descending. There is no parent-category relationship or invented status/currency table.

| Endpoint | Parameters | Data and limits |
| --- | --- | --- |
| `/home` | none | categories <=12 (name/ID order), featured <=8 and latest <=8 (ID descending); three bounded joined queries |
| `/categories` | `page,per_page` | active categories, ID/name/image only; no misleading all-status listing counts |
| `/countries` | `page,per_page` | active countries, ID/name/short name/image; null/blank name falls back to short name |
| `/cities` | `country_id,search,page,per_page` | active cities in active countries; optional exact country; literal case-insensitive name substring according to existing DB collation |
| `/currencies` | `page,per_page` | distinct nonblank `UPPER(TRIM(countries.currency_name))` from active countries, sorted code; no exchange rates or inferred currency table |
| `/listings` | `search,category_id,from_country_id,to_country_id,from_city_id,to_city_id,ad_type,weight,departure_on,sort,page,per_page` | eligible public cards |
| `/listings/{id}` | none | eligible public card plus plain-text `description` |

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
{"success":true,"message":"Categories retrieved successfully.","data":{"items":[{"id":1,"name":"Parcels","image_url":null}],"pagination":{"current_page":1,"per_page":50,"total":1,"last_page":1}}}
```

`GET /api/v1/countries`:
```json
{"success":true,"message":"Countries retrieved successfully.","data":{"items":[{"id":1,"name":"Alpha","short_name":"AA","image_url":null}],"pagination":{"current_page":1,"per_page":50,"total":1,"last_page":1}}}
```

`GET /api/v1/cities?country_id=1`:
```json
{"success":true,"message":"Cities retrieved successfully.","data":{"items":[{"id":1,"country_id":1,"name":"Alpha City","short_name":null}],"pagination":{"current_page":1,"per_page":50,"total":1,"last_page":1}}}
```

`GET /api/v1/currencies`:
```json
{"success":true,"message":"Currencies retrieved successfully.","data":{"items":[{"code":"USD"}],"pagination":{"current_page":1,"per_page":50,"total":1,"last_page":1}}}
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

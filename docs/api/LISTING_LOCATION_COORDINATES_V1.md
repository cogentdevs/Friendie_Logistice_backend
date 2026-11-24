# Listing location coordinates — API v1

Status: **IMPLEMENTATION COMPLETE — MIGRATION PENDING; runtime tests pending.**

Prepared and verified on 24 November 2026. Under the subsequent explicit execution approval, Codex ran only the targeted coordinate migration in the isolated schema and then the original local schema, after focused/regression tests, actual isolated HTTP checks and fresh backup/restore verification. Existing business records were not rewritten. No installation, commit or deployment occurred.

## Scope and endpoints

Existing endpoints, unchanged methods/paths:

| Method | Path | Coordinates |
| --- | --- | --- |
| POST | `/api/v1/customer/listings` | Optional pairs; existing 201 owner listing response |
| PUT | `/api/v1/customer/listings/{id}` | Optional pair replacement/clearing; existing 200 response |
| GET | `/api/v1/customer/listings/{id}` | Own exact coordinates as numbers/null |
| GET | `/api/v1/customer/listings` | Own listing items use the same owner serializer |

No new route, map URL, geography filter, distance calculation, geocoding or mobile UI is added. All existing field names, envelopes, eligibility, status1 creation, pagination and DELETE409 remain intact. Description sanitization, image handling and reference APIs are unchanged.

Headers: `Accept: application/json`; JSON bodies also `Content-Type: application/json`; protected endpoints require `Authorization: Bearer {{customer_token}}`. Only active, verified, exclusively customer-role accounts with an eligible Sanctum token qualify. Owner checks still return404 for another customer's listing; no web session substitute.

## Schema and manual prerequisite

Actual table: `post_ads`. New migration:

`database/migrations/2026_11_24_000001_add_coordinates_to_post_ads_table.php`

Exactly four nullable `DECIMAL(10,7)` columns, default NULL:

- `from_latitude`
- `from_longitude`
- `to_latitude`
- `to_longitude`

No backfill, indexes, tables, triggers or changes to existing columns. Old rows receive NULL. Existing area/address/country/city data remains separate. Rollback drops the four columns and permanently destroys saved pins; never run it without separate approval.

Schema availability is checked without caching across requests/deployment transitions. Before migration (including partial coordinate infrastructure), legacy API requests without pins and website/admin edits still work. Owner responses show null pin fields. Any API request explicitly supplying a coordinate field while infrastructure is unavailable returns422; values are never silently ignored. After migration, owner queries explicitly select these columns; public queries remain unchanged.

## Validation, representation and precision

Both locations are independently optional. Latitude range is -90..90; longitude -180..180, inclusive. Zero is valid. Each touched pair must contain **both keys**, either both null or both valid numbers. A single key, a null/non-null mixture, or an incomplete update is422. An update never combines a new axis with an old stored axis.

Accepted: JSON integers/floating-point numbers and plain decimal numeric strings, e.g. `"24.8607343"`, `"0"`, `"-33.8688197"`. At most seven decimal places. String scientific notation, plus-prefixed numbers, malformed separators, booleans, arrays/objects, NaN/Infinity and out-of-range values are rejected. JSON numeric exponent notation is already converted to a number by the JSON parser: its numeric value must lie on the seven-decimal grid. Binary floating-point comparison permits only representational error at machine precision; valid values are formatted to fixed seven decimals before storage. No rounding of invalid precision or range to make it valid.

Laravel's existing trimming/empty-to-null middleware remains: whitespace-trimmed numeric form strings work; both empty form inputs normalize to null. Strings `"null"`, `"NaN"`, `"Infinity"` are invalid, not clearing syntax. For clearing use JSON null/null or two empty form fields. Owner JSON uses numbers/null, not decimal strings. Trailing zero formatting is not guaranteed in JSON.

## Create, update and stale pins

| Request | Result |
| --- | --- |
| Create with no pins | All four NULL after migration |
| Create with only complete From pair | From saved, To NULL |
| Create with only complete To pair | To saved, From NULL |
| Both complete pairs | Both saved |
| Pair explicitly null/null | No pin / clear that pin |
| PUT omits pins and geography unchanged | Existing pins preserved |
| PUT supplies complete pair | Replace that pair only |
| PUT changes country/city, omits replacement pair | Clear corresponding pair |
| PUT changes geography and supplies complete replacement | Save replacement |
| PUT supplies same geography IDs | Preserve pin |
| PUT changes unrelated fields | Preserve both pairs |
| Invalid request |422, no partial listing update |

Country/city IDs are never inferred from coordinates. Existing API reference/city-country validation still applies. No external geographical containment check verifies that a chosen pin actually lies in the stated city; the client/user must select a sensible pin.

API validation/comparison runs inside the existing customer/listing transaction using the latest locked listing row. Shared `PostAdsController::editPostAds()` now validates/normalizes only submitted location IDs for pin comparison, uses the same listing `FOR UPDATE` lock, compares the current row and updates IDs/cleared pins atomically. Missing IDs are not treated as changes. Unrelated website/admin edits preserve both pins. No coordinate website inputs exist, so website/admin geography changes clear the affected pin rather than accepting a client replacement. The narrow exception does not add country/city eligibility rules to the legacy website. Malformed/nonpositive/out-of-INT-range submitted IDs are rejected for safe comparison when pin schema exists.

Image processing stays in its existing position before the narrow database transaction; no file lifecycle or filesystem rollback guarantee is added. Existing legacy web authorization weaknesses are not repaired by this feature. No model hooks or global lifecycle behavior are introduced.

## Exact-pin authorization

| Viewer/context | Exact pin access |
| --- | --- |
| Owner using authorized customer listing API | Allowed |
| Guest/public endpoint, including an owner calling a guest endpoint | All four fields omitted |
| Authenticated non-owner | Hidden; owner endpoint404 |
| Chat participant / review participant | No entitlement |
| Pending/rejected request | No entitlement; no such verified request workflow exists |
| Accepted user | **Blocked capability:** no reliable accepted-request mechanism exists |

Public home, listing collection/detail/search, chat and reviews use existing explicit projections/serializers, not the new owner projection. They contain no coordinate fields or generated Maps links. Owner responses retain `Cache-Control: no-store, private`. Login alone, published status, chat membership and reviews never grant pin/contact access. Existing phone/email rules are unchanged. Voluntarily typed locations in message/review/description text or images are outside structured-coordinate serialization guarantees.

## JSON create example

`POST {{base_url}}/customer/listings`, where `base_url` includes `/api/v1`:

```json
{
  "ad_type": "Carring",
  "title": "Parcel capacity",
  "description": "<p>Space for a parcel.</p>",
  "category_id": 1,
  "from_country_id": 1,
  "from_city_id": 1,
  "from_area": "Origin district",
  "from_address": "Example origin building",
  "to_country_id": 2,
  "to_city_id": 2,
  "to_area": "Destination district",
  "to_address": "Example destination building",
  "weight": 5,
  "weight_price": 20,
  "currency": "USD",
  "departure_on": "2030-01-02",
  "reached_on": "2030-01-03",
  "from_latitude": 24.8607343,
  "from_longitude": 67.0011364,
  "to_latitude": 31.5203696,
  "to_longitude": 74.3587473
}
```

IDs/currency are synthetic and must be replaced with eligible existing references. Existing `Send` type also supported. Existing `show_contact_info` remains optional and0 only.

Illustrative full201 owner response (media URL may be null):

```json
{
  "success": true,
  "message": "Listing created successfully.",
  "data": {
    "id": 10,
    "category_id": 1,
    "from_country_id": 1,
    "from_city_id": 1,
    "to_country_id": 2,
    "to_city_id": 2,
    "weight": 5,
    "ad_type": "Carring",
    "title": "Parcel capacity",
    "description": "<p>Space for a parcel.</p>",
    "currency": "USD",
    "from_area": "Origin district",
    "to_area": "Destination district",
    "from_address": "Example origin building",
    "to_address": "Example destination building",
    "departure_on": "2030-01-02",
    "reached_on": "2030-01-03",
    "created_at": "2030-01-01 12:00:00",
    "updated_at": "2030-01-01 12:00:00",
    "from_latitude": 24.8607343,
    "from_longitude": 67.0011364,
    "to_latitude": 31.5203696,
    "to_longitude": 74.3587473,
    "weight_price": 20,
    "status": 1,
    "is_featured": false,
    "show_contact_info": 0,
    "image_url": null
  }
}
```

GET owner details has the same data fields,200 and message `Your listing retrieved successfully.` PUT has200 and `Listing updated successfully.` Missing pins are four null fields. Unmigrated fallback also returns four nulls for legacy requests.

## Update and multipart examples

Replace From only:

```http
PUT /api/v1/customer/listings/10
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{customer_token}}
```

```json
{"from_latitude":0,"from_longitude":0}
```

Clear To only:

```json
{"to_latitude":null,"to_longitude":null}
```

Multipart POST creation uses all required existing fields from the JSON create example as text parts; optional areas/addresses/description and weight/price also as text. Coordinate text parts:

```text
ad_type=Carring
title=Parcel capacity
category_id=1
from_country_id=1
from_city_id=1
to_country_id=2
to_city_id=2
currency=USD
departure_on=2030-01-02
reached_on=2030-01-03
from_latitude=24.8607343
from_longitude=67.0011364
to_latitude=31.5203696
to_longitude=74.3587473
```

No image part: images still use the separate existing image endpoint. Let the client generate multipart boundaries. For updates use JSON PUT. Raw multipart PUT parsing is server/PHP-dependent, and the existing strict listing input allowlist rejects `_method`; method-spoofed form updates are therefore not promised by this contract. Multipart POST creation is supported; coordinate decimal strings are accepted by the same validator. Both empty form coordinate fields normalize to null. No parsing workaround, new route or unrelated allowlist change is introduced.

## Errors and public examples

Incomplete pair,422:

```json
{"success":false,"message":"Validation failed","errors":{"from_latitude":["Supply both latitude and longitude, or set both to null."]}}
```

Range/precision/type error,422:

```json
{"success":false,"message":"Validation failed","errors":{"to_latitude":["Use a finite decimal between -90 and 90, with at most 7 decimal places."]}}
```

Missing coordinate schema,422 for supplied fields:

```json
{"success":false,"message":"Validation failed","errors":{"from_latitude":["Location pins are not available yet. Please try again after the server update."],"from_longitude":["Location pins are not available yet. Please try again after the server update."]}}
```

Existing401: `{"success":false,"message":"Unauthenticated"}`;403: `{"success":false,"message":"Forbidden"}`; missing/non-owner404: `{"success":false,"message":"Resource not found"}`. Existing429/500 handling remains. SQL traces are not returned.

Empty public-home example (no exact-pin fields):

```json
{"success":true,"message":"Home retrieved successfully.","data":{"categories":[],"featured_listings":[],"latest_listings":[]}}
```

Populated public listing cards retain existing `origin`/`destination` country/city IDs/names only. Even an authenticated owner receives no exact pins through the public serializer. Use owner details for exact pins.

## Mobile boundary

Bare React Native CLI handles GPS permission, Current Location, selection, maps and opening/copying links locally. Backend needs no Maps key, SDK or geocoding dependency. Client may construct a Maps link only after authorized owner coordinates are received; never publish or log private pins/bearer tokens. Pins do not replace country/city selection.

## Manual commands — USER EXECUTES; none executed by Codex

1. Navigate and confirm current environment. Do not paste passwords into command lines/chat:

```powershell
Set-Location G:/friendie_logistics_project/friendie_logistics_backend
$dbUser = 'YOUR_DATABASE_USERNAME'
& 'C:/xampp/mysql/bin/mysql.exe' --host=127.0.0.1 --port=3306 "--user=$dbUser" --password --database=friendie_logistics --execute="SELECT DATABASE(); SHOW FULL TABLES; SHOW COLUMNS FROM post_ads; SHOW INDEX FROM post_ads; SELECT COUNT(*) AS listing_count FROM post_ads; SELECT migration,batch FROM migrations WHERE migration='2026_11_24_000001_add_coordinates_to_post_ads_table';"
```

2. Fresh backup outside project/public paths; quiet maintenance window, no concurrent DDL. Current original tables are InnoDB. Check privileges for routines/events/triggers. Do not overwrite an existing backup:

```powershell
$backupDir = 'G:/friendie_logistics_safety/coordinates_' + (Get-Date -Format 'yyyyMMdd_HHmmss')
New-Item -ItemType Directory -Path $backupDir -ErrorAction Stop
$backupFile = Join-Path $backupDir 'friendie_logistics.sql'
& 'C:/xampp/mysql/bin/mysqldump.exe' --host=127.0.0.1 --port=3306 "--user=$dbUser" --password --single-transaction --quick --routines --events --triggers --hex-blob --default-character-set=utf8mb4 "--result-file=$backupFile" friendie_logistics
if ($LASTEXITCODE -ne 0) { throw 'Backup failed; do not migrate.' }
Get-Item -LiteralPath $backupFile
Get-FileHash -LiteralPath $backupFile -Algorithm SHA256
```

A checksum/nonempty file is not restorability proof. Restore this dump to a separately configured disposable MariaDB instance (e.g. port3307), events disabled, no application connections. Never restore into the original schema or isolated test database. Example only after that disposable instance exists:

```powershell
$restoreUser = 'DISPOSABLE_INSTANCE_USER'
& 'C:/xampp/mysql/bin/mysql.exe' --host=127.0.0.1 --port=3307 "--user=$restoreUser" --password --execute="CREATE DATABASE friendie_coordinates_restore_verify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$restorePath = $backupFile.Replace('\','/')
& 'C:/xampp/mysql/bin/mysql.exe' --host=127.0.0.1 --port=3307 "--user=$restoreUser" --password --database=friendie_coordinates_restore_verify "--execute=SOURCE $restorePath"
if ($LASTEXITCODE -ne 0) { throw 'Restore verification failed; do not migrate.' }
```

Compare restored table/view inventory, DDL/indexes, exact per-table counts/content fingerprints and routines/triggers/events with the backup-time baseline. `information_schema.TABLES.TABLE_ROWS` is an estimate, not exact integrity proof.

3. Confirm isolated credentials have only the dedicated test schema and denied access to original. Clear any ambient DB overrides before test commands; inspect cached config before any migration. User executes the isolated migration first:

```powershell
php artisan migrate:status --env=testing --database=mysql --path=database/migrations/2026_11_24_000001_add_coordinates_to_post_ads_table.php
php artisan migrate --env=testing --database=mysql --path=database/migrations/2026_11_24_000001_add_coordinates_to_post_ads_table.php
php vendor/bin/phpunit --filter ListingCoordinatesTest --do-not-cache-result --display-deprecations
php vendor/bin/phpunit --filter 'ListingCoordinatesTest|CustomerListingsTest|PublicApiTest|ReferenceDataApiTest|CustomerProfileTest|CustomerChatTest|CustomerReviewTest|MobileAuthenticationTest|EmailActivationTest|EmailActivationConcurrencyTest|WebPasswordResetTest|PasswordResetConcurrencyTest|ApiFoundationTest|SafetyEnvironmentTest' --do-not-cache-result --display-deprecations
```

Do not use the unrestricted legacy/default test suite: some historical tests have destructive migration traits. Tests contain no migration/DDL/seed commands; post-migration cases explicitly skip when schema is missing. A missing-schema case mocks availability without altering the schema. Do not report skipped cases as passed feature coverage.

4. Only after verified fresh backup/restore, identity checks, isolated tests and user review, apply to original in a quiet deployment window:

```powershell
php artisan migrate:status --database=mysql --path=database/migrations/2026_11_24_000001_add_coordinates_to_post_ads_table.php
php artisan migrate --database=mysql --path=database/migrations/2026_11_24_000001_add_coordinates_to_post_ads_table.php
php artisan migrate:status --database=mysql --path=database/migrations/2026_11_24_000001_add_coordinates_to_post_ads_table.php
& 'C:/xampp/mysql/bin/mysql.exe' --host=127.0.0.1 --port=3306 "--user=$dbUser" --password --database=friendie_logistics --execute="SHOW COLUMNS FROM post_ads; SHOW INDEX FROM post_ads; SHOW FULL TABLES; SELECT COUNT(*) AS listing_count FROM post_ads; SELECT COUNT(*) AS rows_with_any_pin FROM post_ads WHERE from_latitude IS NOT NULL OR from_longitude IS NOT NULL OR to_latitude IS NOT NULL OR to_longitude IS NOT NULL;"
```

Expected: same26 tables/18 listings as inspected baseline unless legitimate user activity occurred; exactly four added nullable decimal columns, no new index; all old rows NULL; one migration ledger entry. DDL may lock/rebuild the table depending on MariaDB; no zero-downtime guarantee. Existing-column data fingerprints must be compared separately from changed table DDL. Review partial-failure state before retrying; do not blindly roll back/drop columns.

## Verification status and boundaries

Prepared tests cover valid/invalid pairs, boundaries, zero/negative/fractional values, strings, optional/null pins, owner serialization, clear/preserve/replace behavior, stale-pin clearing, public/chat/review omission, pre-migration fallback, web/admin unchanged/missing/changed IDs, invalid updates, database failure rollback and competing-row-lock exclusion. They do not constitute production concurrency/load certification. Website's pre-existing authorization/media limitations remain outside scope.

At preparation both schemas lacked coordinates. Verification is now complete: isolated migration batch3 and original migration batch8 succeeded, each exit0. All four nullable DECIMAL(10,7) columns exist and the migration is tracked exactly once in each schema. The original26 tables/18 listings remain; original rows have NULL pins. Browser/physical-Android interaction and Maps/GPS UI were not tested or implemented. Accepted-request access remains unsupported.

Static verification: six affected PHP files passed syntax checks; Git whitespace checks passed. Thirty-seven in-memory helper checks passed using fake Schema/Validator dependencies, with no application boot/database connection: bounds, zero, decimal strings, small/negative values, malformed/nonfinite/incomplete pairs, clear/preserve/replacement and unavailable-schema behavior. These are not PHPUnit integration results. Original26-table/18-listing fingerprint before/after matched (`e2746880f4307b68d2111d345f869d456c77d924dbf7ac951149d47feea5c74d`);43 protected media hashes matched. Routes source inventory remains37; route file and all Blade/CSS/JS/auth/chat/review files are unchanged.


## Final executed verification ? 24 November 2026

- Focused PHPUnit: **38 tests / 682 assertions**, zero failures/skips; duration32.486s.
- Full safe regression: **434 tests / 4319 assertions**, zero failures/skips; duration57.523s. One pre-existing PHP8.4 voku/portable-ascii nullable-parameter deprecation.
- Actual local HTTP integration: **23 request/response checks passed** against a loopback-only isolated Laravel server using real Sanctum bearer tokens and synthetic fixtures. JSON create/update/details, owner list, persisted values, independent replacement/clearing, geography clearing, invalid/incomplete422, guest/public privacy, nonowner404, real multipart numeric strings/empty normalization and separate image upload preserving pins were exercised. DELETE remains409. Task servers were stopped and synthetic fixtures cleaned.
- Lock test: API and web/admin edit each held the same listing-row lock; a second independent PDO connection using FOR UPDATE NOWAIT was rejected with1205. This verifies lock exclusion, not all possible production interleavings or load behavior.
- Original migration: targeted path only; exit0; batch8. No historical migration or rollback was run.
- Fresh dump: G:/friendie_logistics_safety/coordinates_20261124_141216_6916d3/friendie_logistics.sql;780926bytes; SHA256 e8fc31523b5855b82b565736da073c0b2407fa2d86fcada6abad194902cf2503.
- Restricted disposable restore schema: friendie_coord_restore_20261124_99e8db51. Restore exit0; all26 table schemas/data fingerprints matched the dump-time original. Restore credentials were denied original-database access; temporary credential files were removed. Original had0views/triggers/routines/events; dump included the relevant options and was inspected for database switching/qualified cross-database writes. Clone is retained for verification, not connected to the app.
- First disposable schema creation attempt was rejected because the inspection connection was read-only; no original mutation occurred. A separate connection was used for the approved disposable schema creation, after rechecking original/backup identity.
- Post-migration:24 unrelated tables unchanged including tokens; original post_ads existing-column data hashes and pre-existing DDL/indexes unchanged; old migration records unchanged; precisely four columns plus one migration entry added. All43 protected media hashes match.
- No other project database business data was read or modified. Only schema names were inventoried; no claim of full fingerprints for unrelated databases.

### Concise mobile handover

Use JSON POST/PUT with both keys for each touched pair. Omit both to preserve on update; null/null clears; zero/zero is a real pin. Owner GET/details and own list return numbers/null. Public responses omit the fields entirely, including when an owner calls a public endpoint. Handle422 and401/403/404 normally. Select country/city independently; on geography change provide a complete replacement or expect the stale pair to clear. Native GPS, maps and clipboard require only client implementation. A copied/shared external coordinate link discloses that location outside the app; the backend cannot revoke an already copied geographic value/link. Accepted-user exact-pin access is NOT IMPLEMENTED. This is readiness for this owner-only feature, not a blanket production/browser/device certification.

### Actual isolated HTTP responses

These are captured synthetic test responses, not original customer data. Actual IDs/timestamps are test-run values; do not reuse them as live records.

#### POST /customer/listings ? 201

```json
{
    "success": true,
    "message": "Listing created successfully.",
    "data": {
        "id": 3463,
        "category_id": 6391,
        "from_country_id": 12217,
        "from_city_id": 7284406,
        "to_country_id": 12217,
        "to_city_id": 7284406,
        "weight": 5,
        "ad_type": "Carring",
        "title": "Parcel capacity",
        "description": "<p>Safe</p>",
        "currency": "USD",
        "from_area": null,
        "to_area": null,
        "from_address": null,
        "to_address": null,
        "departure_on": "2026-11-25",
        "reached_on": "2026-11-26",
        "created_at": "2026-11-24 09:16:11",
        "updated_at": "2026-11-24 09:16:11",
        "from_latitude": 24.8607343,
        "from_longitude": 67.0011364,
        "to_latitude": 31.5203696,
        "to_longitude": 74.3587473,
        "weight_price": 20,
        "status": 1,
        "is_featured": false,
        "show_contact_info": 0,
        "image_url": null
    }
}
```

#### PUT /customer/listings/{id} ? 200 (From zero replacement)

```json
{
    "success": true,
    "message": "Listing updated successfully.",
    "data": {
        "id": 3463,
        "category_id": 6391,
        "from_country_id": 12217,
        "from_city_id": 7284406,
        "to_country_id": 12217,
        "to_city_id": 7284406,
        "weight": 5,
        "ad_type": "Carring",
        "title": "Parcel capacity",
        "description": "<p>Safe</p>",
        "currency": "USD",
        "from_area": null,
        "to_area": null,
        "from_address": null,
        "to_address": null,
        "departure_on": "2026-11-25",
        "reached_on": "2026-11-26",
        "created_at": "2026-11-24 09:16:11",
        "updated_at": "2026-11-24 09:16:12",
        "from_latitude": 0,
        "from_longitude": 0,
        "to_latitude": 31.5203696,
        "to_longitude": 74.3587473,
        "weight_price": 20,
        "status": 1,
        "is_featured": false,
        "show_contact_info": 0,
        "image_url": null
    }
}
```

#### GET /customer/listings/{id} ? 200 owner

```json
{
    "success": true,
    "message": "Your listing retrieved successfully.",
    "data": {
        "id": 3463,
        "category_id": 6391,
        "from_country_id": 12217,
        "from_city_id": 7284406,
        "to_country_id": 12217,
        "to_city_id": 7284406,
        "weight": 5,
        "ad_type": "Carring",
        "title": "Parcel capacity",
        "description": "<p>Safe</p>",
        "currency": "USD",
        "from_area": null,
        "to_area": null,
        "from_address": null,
        "to_address": null,
        "departure_on": "2026-11-25",
        "reached_on": "2026-11-26",
        "created_at": "2026-11-24 09:16:11",
        "updated_at": "2026-11-24 09:16:11",
        "from_latitude": 24.8607343,
        "from_longitude": 67.0011364,
        "to_latitude": 31.5203696,
        "to_longitude": 74.3587473,
        "weight_price": 20,
        "status": 1,
        "is_featured": false,
        "show_contact_info": 0,
        "image_url": null
    }
}
```

#### GET /customer/listings ? 200 owner

```json
{
    "success": true,
    "message": "Your listings retrieved successfully.",
    "data": {
        "items": [
            {
                "id": 3463,
                "category_id": 6391,
                "from_country_id": 12217,
                "from_city_id": 7284406,
                "to_country_id": 12217,
                "to_city_id": 7284407,
                "weight": 5,
                "ad_type": "Carring",
                "title": "Updated parcel capacity",
                "description": "<p>Safe</p>",
                "currency": "USD",
                "from_area": null,
                "to_area": null,
                "from_address": null,
                "to_address": null,
                "departure_on": "2026-11-25",
                "reached_on": "2026-11-26",
                "created_at": "2026-11-24 09:16:11",
                "updated_at": "2026-11-24 09:16:16",
                "from_latitude": 24.8607343,
                "from_longitude": 67.0011364,
                "to_latitude": null,
                "to_longitude": null,
                "weight_price": 20,
                "status": 1,
                "is_featured": false,
                "show_contact_info": 0,
                "image_url": null
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 1,
            "last_page": 1
        }
    }
}
```

#### GET /listings/{id} ? 200 public

```json
{
    "success": true,
    "message": "Listing retrieved successfully.",
    "data": {
        "id": 3463,
        "ad_type": "Carring",
        "title": "Updated parcel capacity",
        "short_description": "Safe",
        "image_url": null,
        "category": {
            "id": 6391,
            "name": "Parcels"
        },
        "origin": {
            "country_id": 12217,
            "country": "Alpha",
            "city_id": 7284406,
            "city": "Alpha City"
        },
        "destination": {
            "country_id": 12217,
            "country": "Alpha",
            "city_id": 7284407,
            "city": "Beta City"
        },
        "weight": 5,
        "weight_price": 20,
        "currency": "USD",
        "departure_on": "2026-11-25",
        "reached_on": "2026-11-26",
        "is_featured": false,
        "created_at": "2026-11-24 09:16:11",
        "owner": {
            "id": 13073,
            "display_name": "Synthetic Owner",
            "image_url": null
        },
        "description": "Safe"
    }
}
```

#### PUT invalid latitude ? 422

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "from_latitude": [
            "Use a finite decimal between -90 and 90, with at most 7 decimal places."
        ]
    }
}
```

#### PUT incomplete pair ? 422

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "to_latitude": [
            "Supply both latitude and longitude, or set both to null."
        ]
    }
}
```

#### GET other owner listing ? 404

```json
{
    "success": false,
    "message": "Resource not found"
}
```

#### POST multipart decimal strings ? 201

```json
{
    "success": true,
    "message": "Listing created successfully.",
    "data": {
        "id": 3465,
        "category_id": 6391,
        "from_country_id": 12217,
        "from_city_id": 7284406,
        "to_country_id": 12217,
        "to_city_id": 7284406,
        "weight": 5,
        "ad_type": "Carring",
        "title": "Parcel capacity",
        "description": "<p>Safe</p>",
        "currency": "USD",
        "from_area": null,
        "to_area": null,
        "from_address": null,
        "to_address": null,
        "departure_on": "2026-11-25",
        "reached_on": "2026-11-26",
        "created_at": "2026-11-24 09:16:21",
        "updated_at": "2026-11-24 09:16:21",
        "from_latitude": 24.8607343,
        "from_longitude": 67.0011364,
        "to_latitude": null,
        "to_longitude": null,
        "weight_price": 20,
        "status": 1,
        "is_featured": false,
        "show_contact_info": 0,
        "image_url": null
    }
}
```

#### POST multipart image ? 200

```json
{
    "success": true,
    "message": "Listing image updated successfully.",
    "data": {
        "image_url": "http://127.0.0.1:18084/frontend/img/listing/api_listing_3463_d1547330fd5006b16449c452fa87a1e5.jpg"
    }
}
```

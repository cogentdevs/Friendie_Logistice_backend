# Friendie Logistics — Customer Reviews API v1

The approved completed-deal Phase 8A contract below supersedes the earlier listing-only
submission/public-visibility rules documented later in this file. Historical sections are
retained as prior-phase evidence. Examples are synthetic.

## Current Phase 8A: completed-deal reviews and public listing ratings

Existing review storage, 0..5 integer ratings (including zero), required nonempty review
text, NUL rejection and LONGTEXT compatibility are reused. No review editing, deletion,
moderation, email, notification or new dependency is introduced.

### Submit a completed-deal review

`POST {{base_url}}/customer/reviews`, eligible customer Sanctum bearer authentication:

```json
{"deal_id":51,"rating":5,"review":"Helpful communication."}
```

Required fields are now **deal_id, rating, review**. The old `post_ads_id` mobile payload
is no longer accepted; client reviewer/recipient/listing identities and unknown fields
return 422. Existing customer-only, active/verified, mobile token and 10-attempts-per-15-
minutes account/IP protections remain. A valid completed deal must identify the caller
as its stored sender or carrier. Missing, unrelated and noncompleted deals return the
same 404 without listing metadata. Corrupt self-deals cannot create reviews (409).

Reviewer is the authenticated user; recipient is the other stored deal participant.
A sender reviews the carrier's listing; a carrier reviews the sender's listing. Current
listing ownership, route edits, expiry, publication and deletion cannot redirect that
recipient or prevent an otherwise authorized completed-deal review. A chat room is not
required. A completed deal is required even when the target listing is publicly visible.

Success remains HTTP 201, `Review submitted successfully.`, with the existing explicit
review object plus nullable/integer `deal_id`. Its other fields remain `id`, `listing`
`{id,title}`, `reviewer` `{id,display_name}`, `reviewed_customer` `{id,display_name}`,
`rating`, `review`, `date_time`, `created_at`, `updated_at`. Duplicate reviewer/deal
submissions return 409 `You have already reviewed this deal.` without overwriting data.
Either participant may submit one independent review; another completed deal permits a
new review even for the same listing. Legacy listing/reviewer duplicates do not block a
new verified deal review.

The shared writer locks the authenticated reviewer, then completed deal, checks duplicates
and inserts in one transaction with three deadlock retries. API token revocation is
rechecked while the reviewer is locked. Website and API submissions use this same lock
and unique key; storage failure rolls back without changing any deal, capacity, listing,
chat or notification state. Recipient eligibility is not retroactively reinterpreted;
stored deal identities remain usable when the counterpart account is inactive/deleted.

### Authenticated history

`GET /customer/reviews/given` and `GET /customer/reviews/received` retain customer scoping,
newest-review-ID ordering, `page`, and `per_page` (20 default, 50 maximum). Existing legacy
rows have `deal_id:null` and are preserved without backfill/deduplication. Missing parent
listing/reviewer rows now remain in authenticated API history with unavailable labels
null. New deal-linked reviews use safe recorded counterpart listing titles and participant
names from the authorized deal snapshots, preserving history after editing/deletion.
Fields missing from older snapshots stay null; no historical names are fabricated.

Private history retains existing raw review-text compatibility; display as plain text,
never trusted HTML. No phone/email account fields, exact coordinates or addresses are
selected or serialized. GET performs no writes. Existing website history pages are not
changed and retain their existing join/display behavior.

### Public listing reviews and average

`GET {{base_url}}/listings/102/reviews?page=1&per_page=20`, no authentication required.
The target listing must pass unchanged `PublicListingQuery` visibility. Missing/nonpublic
listings return identical 404. Expired/deleted listing reviews remain privately accessible
through participant history; this endpoint does not expose nonpublic listings.

Success is HTTP 200 with the usual envelope and these `data` fields:

```json
{"listing_id":102,"average_rating":4.5,"review_count":2,"items":[{"id":301,"reviewer":{"id":201,"display_name":"Example Sender"},"rating":5,"review":"Helpful communication.","date_time":"2030-01-03","created_at":"2030-01-03 10:00:00","updated_at":"2030-01-03 10:00:00"}],"pagination":{"current_page":1,"per_page":20,"total":2,"last_page":2}}
```

Only rows linked to an existing **completed** deal, with correct reciprocal participant
IDs and counterpart listing ID, a non-self reviewer and rating 0..5 are included. The
review recipient must also match the target listing's current owner, avoiding attributing
historical feedback to a later owner. Legacy/null, missing-deal, noncompleted and mismatched
rows do not affect public items, average or count. The aggregate covers the entire filtered
listing result, not just the current page; zero ratings count normally. Average is rounded
to one decimal. No reviews means `average_rating:null`, `review_count:0`, `items:[]`.

Pagination defaults to 20, maximum 50, ordered by descending review ID. Unknown query
fields/invalid pagination return 422. Public items omit deal IDs, recipient identities,
private listing metadata and raw snapshots. Names and text use the existing contact-
filtering plain-text serializer; public review text is bounded to 10,000 characters and
contact-bearing text may be null. Clients must render it as plain text. Aggregation and
pagination reuse the same verified scope and joined queries; no per-item queries.

### Minimum website safeguard and remaining UI limitations

Only `UserController::postRateReview()` submission handling changes. Authentication and
eligible-customer rules are enforced, rating/text validated, submitted hidden identities
cross-checked, and target identities derived by the shared writer. Existing form fields,
CSRF/routes, redirects, flash message, Blade, CSS/JS and admin code remain unchanged.
The existing form may omit `deal_id`: exactly one matching completed deal is required.
None returns 404; multiple returns 409 `Select the completed deal explicitly.` Supplying
an explicit deal ID must still match the posted listing and any posted identities.

No UI deal picker is added. Existing website listing-based duplicate-button disabling
and legacy owner-wide website rating calculations remain unchanged; they can limit web
submission for later deals or differ from this verified listing-scoped API aggregate.
These presentation changes require separate approval. New API requests can select deals
explicitly. Website/HTML rendering and real browser workflows were not redesigned.

### Approved schema and manual deployment

Migration adds only nullable BIGINT UNSIGNED `reviews.deal_id`, unique
`(deal_id,from_user_id)` and pagination index `(post_ads_id,id)`. There are no foreign-key
cascades or data rewrites; null legacy rows remain compatible with the unique index.
After validating the database target and backup, the owner manually runs:

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_11_25_000004_add_deal_id_to_reviews_table.php
```

This migration was not executed against the original database. Until deployed, new API
and website review writes and the new public review endpoint fail safely with 503.
Authenticated legacy API history still works. Rollback loses review/deal provenance and
must not be performed blindly on a populated database. No original reviews are backfilled.

## Verified schema and website evidence

Actual MariaDB `reviews` columns:

| Column | Actual type / meaning |
| --- | --- |
| id | Unsigned BIGINT primary key |
| post_ads_id | Required INT, listing reference |
| from_user_id | Required INT, reviewer |
| to_user_id | Required INT, reviewed account |
| rating | Required INT |
| review | Nullable LONGTEXT |
| date_time | Required VARCHAR(255), web writes Y-m-d |
| created_at, updated_at | Nullable TIMESTAMP |

Only the primary-key index exists; no review foreign key, duplicate unique key, approval, moderation or acceptance field. `Review` model has HasFactory only and no declared relationships. No edit/delete review workflow was found in inspected routes/controllers.

Source evidence:

- `routes/web.php`: GET `/user/reviews`, POST `/user/post-review`.
- `UserController::postRateReview()`: inserts posted listing/from/to/rating/text and date, then redirects. No server-side input/eligibility/duplicate validation, mail or notification exists in that method.
- `FrontController::listing_details()`: target is listing.created_by; duplicate count is listing ID plus current reviewer ID, regardless of recipient; owner rating is AVG(rating) by to_user_id rounded for presentation.
- `resources/views/frontend/listing-details.blade.php`: customer-role form, required review textarea, hidden reviewer/current listing owner, disables submit for self-review or existing reviewer/listing review. This section has no publication/date/chat/acceptance/completed-delivery condition.
- Star choices are 1..5 (`public/frontend/js/customAjax.js` writes the selected value); hidden input starts at **0** and star selection is not required. API preserves the form's representable **0..5** range, including the existing unselected-zero behavior. It does not silently impose a new 1..5-only policy. Existing controller can accept arbitrary values; that vulnerability is not copied or fixed globally.
- Web textarea has no maxlength; database is LONGTEXT. API requires nonempty text but adds no invented business-length maximum. Existing HTTP/server request-size limits still apply; adding a new product-specific cap needs separate approval.
- `UserController::reviews()` lists received by to_user_id and given by from_user_id, joining listing and reviewer. No status/approval filtering. Listing/reviewer orphan rows are absent from joined results, while historical counts may include them.

**Approved Phase 10A change:** NEW mobile API reviews now require the target listing to pass the unchanged `PublicListingQuery::make()` visibility criteria. This is an API-only submission restriction; the website rules described above and existing history are unchanged. No chat, accepted request, completed delivery or new target-account verification requirement is added. A valid listing owner remains the only recipient; arbitrary customer IDs are not accepted. Existing legacy rows are not repaired, deduplicated or reclassified.

## Authentication and headers

Base: `{{base_url}} = https://api.example.test/api/v1` (replace with configured reachable origin).

```text
Accept: application/json
Authorization: Bearer {{access_token}}
Content-Type: application/json
```

All three routes require existing `auth:sanctum` and `EligibleMobileCustomer`: active, verified, exclusively customer-role caller, valid personal access token and mobile account ability. Existing web sessions do not substitute. GETs reuse read-only Sanctum, avoiding token last-used writes.

## GET /customer/reviews/received

Reviews addressed to authenticated account only. No customer ID is accepted. Optional query `page` (1..100000, default 1) and `per_page` (1..50, default 20). Unknown filters return 422.

```json
{
  "success": true,
  "message": "Received reviews retrieved successfully.",
  "data": {
    "items": [{
      "id": 1,
      "listing": {"id": 10, "title": "Parcel capacity"},
      "reviewer": {"id": 2, "display_name": "Example Reviewer"},
      "reviewed_customer": {"id": 3, "display_name": "Example Owner"},
      "rating": 4,
      "review": "Helpful communication.",
      "date_time": "2026-11-14",
      "created_at": "2026-11-14 12:00:00",
      "updated_at": "2026-11-14 12:00:00"
    }],
    "pagination": {"current_page": 1, "per_page": 20, "total": 1, "last_page": 1}
  }
}
```

## GET /customer/reviews/given

Same pagination/item structure, with message `Given reviews retrieved successfully.` Scoped solely to authenticated from_user_id, never an input reviewer ID. Includes actual recipient identity, rather than copying the website's given-review display alias mistake. Existing website display is unchanged.

Both lists order ID descending (stable newest stored first), preserve stored legacy ratings/text/timestamps, and return `items:[]` with last_page 1 when empty. Missing listing/reviewer joins exclude orphan rows as the website does. Missing recipient data yields null display name without exposing a different user. No extra public-listing/status/approval filter is added to historical reviews.

GETs perform SELECT queries only, do not render Blade, update read state, synchronize expiry, mark tokens used, or recalculate stored ratings. Response Cache-Control is no-store/private. Pagination uses bounded queries and joins, not one user lookup per item.

## POST /customer/reviews

```json
{"post_ads_id":10,"rating":4,"review":"Helpful communication."}
```

| Field | Validation |
| --- | --- |
| post_ads_id | Required positive integer <=2147483647, listing eligible under existing public visibility query |
| rating | Required integer 0..5, preserving default-zero form behavior |
| review | Required nonempty text, not array; NUL rejected; no new business-length cap |

Unknown fields rejected, including from_user_id, to_user_id, reviewer_id, customer_id, user_id, approval flags and attachments. Required identity is derived as reviewer=authenticated caller and recipient=listing.created_by. After unchanged input/account validation, missing and nonpublic listings both return the identical HTTP 404 body `{"success":false,"message":"Resource not found"}`. No review is created and no listing/owner metadata is returned. This visibility check occurs under transaction locks BEFORE self-review, recipient lookup, duplicate checks, insertion and response serialization. For a publicly eligible target, self-review still returns 422.

Eligibility is reused directly from `PublicListingQuery::make()`, not copied or redefined: published status1, active category, departure on/after today and all existing required owner/category/country/city joins. Pending0, expired2, past/NULL departure, inactive/missing category and missing required joins cannot receive NEW API reviews. Today's departure remains eligible. No additional active-country/city or owner-verification filter is invented beyond that shared query. `PublicListingQuery` itself is unchanged.

Duplicate rule is the form's exact pair `(post_ads_id, from_user_id)`, not per reviewed user or room. Existing duplicate entries remain visible; another submission against a PUBLICLY eligible target returns **409** `You have already reviewed this listing.` without changing the earlier rating/text. A now-nonpublic duplicate or self-review target returns the same404 as a missing listing, not409/422. The same reviewer can review a different publicly eligible listing belonging to that owner.

Success: HTTP **201**, message `Review submitted successfully.`, with the same explicit review object shown above as `data`. Stores the existing fields and date format; no automatic login, acceptance, chat room, notification or email. Standard database AVG(rating) calculations remain compatible; API does not invent weights/categories or modify existing calculation code.

Transaction locks the caller, rechecks account/token eligibility, locks the listing, checks public eligibility using a locking public query projected to listing ID, then applies existing self/recipient/duplicate checks and inserts. Laravel can retry deadlocks up to three attempts. This serializes participating API submissions. **Global uniqueness cannot be guaranteed against simultaneous legacy website writers**, which have neither a matching lock protocol nor a database unique constraint. No claim of production concurrency testing or globally enforced uniqueness is made.

Mutation limit: **10 attempts per account and per IP per 15 minutes**, including failures. API-only rate limiting does not change website behavior.

## Errors

| Status | Meaning |
| --- | --- |
| 401 | Missing/invalid/revoked token |
| 403 | Ineligible caller / wrong token ability |
| 404 | Missing/nonpublic target (same message/body), missing owner or unsupported route |
| 409 | Already reviewed this publicly eligible listing |
| 422 | Missing/invalid fields, self-review or identity injection |
| 429 | Rate limited; obey Retry-After |
| 500 | Sanitized server error; no SQL/stack trace |

```json
{"success":false,"message":"Validation failed","errors":{"rating":["The rating field must be between 0 and 5."]}}
```

## Privacy and scope

Explicit queries select no user phone, email, password, token or private address fields. Display names/listing titles use the existing public-text sanitizer. Review text is existing participant-authored content, returned as plain text; do not render it as trusted HTML. A user may have typed contact text into a legacy review; this API provides no account-contact lookup or acceptance authorization. Semantic moderation/redaction of arbitrary review text would be a separate policy, not silently introduced here.

The fixed disclosure path was NEW POST submission by guessed nonpublic listing ID, which previously created a review and returned listing title/owner identity, also making that metadata available via subsequent given history. New nonpublic POSTs now produce no review and indistinguishable404 bodies. This does **not** erase historical disclosure: prior reviews, including those created for pending or later-expired listings, remain readable by their existing authorized reviewer/recipient. GET history implementation, pagination and serialization are unchanged. No claim of resistance to all timing-based existence inference is made; malformed payload validation still runs before target lookup, identically for missing/nonpublic IDs.

No review-details route is added: website has listing feedback and own given/received lists, not a separately authorized review details page. No edit/delete/moderation/report/reaction/attachment/public review API, new review category or acceptance workflow. Reviews or chats do not establish acceptance or authorize phone reveal.

Original-database Sanctum token-table deployment remains separately approval-gated. Do not run migrations from this guide. No original records were used for mutating tests. Existing unsafe website posted identities, duplicate bypass, unrestricted ratings and wildcard Blade composer side effects remain unchanged and documented; safe API validation does not fix those web entry points.

## Postman examples

1. Use isolated synthetic accounts, configured bearer-token table and Phase 4 login. Save token privately in Postman.
2. GET `{{base_url}}/customer/reviews/received?page=1&per_page=20` and `/customer/reviews/given` without account IDs.
3. Choose another account's PUBLICLY eligible listing; POST the three JSON fields above. No chat or accepted request is needed.
4. Repeat same reviewer/public listing: expect409. Change reviewer ID via extra field: expect422. Review own public listing: expect422. Missing/nonpublic listing, including self/duplicate nonpublic targets: identical404 and no insertion.
5. Confirm new review appears in caller's given and owner's received lists, and no email/notification or listing/chat changes occur.

Do not export real tokens/private reviews. No physical-device, browser business-flow or production load verification is claimed.

## Verification record — 14 November 2026

- Test preflight verified the dedicated restricted testing identity, original-database access denial, fake mail/notifications/queue and temporary filesystem isolation. Only transactional synthetic fixtures were used; no migration or seeder ran.
- Phase 8: **46 tests / 237 assertions passed**. Combined existing API/security and new reviews regression run: **355 tests / 2,949 assertions passed**, including Phase 7 chat, Phase 6 blocked DELETE, reference nonpagination/ranking, profiles, authentication, activation/reset and web/admin route inventory. One existing PHP 8.4 portable-ascii vendor deprecation remains; no assertion failed.
- PHP syntax checks passed for controller, feature test, API routes and inventory test. Git whitespace check passed.
- Original **25-table** schema/data fingerprint matched before/after (`9a6989e6491ca160ef525257c8ea31f62149a9154f06187f1529b37fbdc9dbe6`). All **43 baseline media hashes** matched. Aggregate checksums for **1,984 protected existing files**, including Phase 7 files, environment files, Composer files, Word document and Git index, matched; only the two deliberately extended route/inventory files were excluded from this aggregate.
- Exactly three new Phase 8 files plus additive changes in the two permitted route/inventory files. Existing staged/uncommitted work preserved. HEAD remains `495fe4b7c753491504654d626b039a0416a9edbb`. No stage, commit, push, deploy or next-phase work performed.
- Current total is **37 registered API routes**, including the still-blocked listing DELETE. Existing public/Word overview remains untouched under this task's strict file boundary; use this document for the three new review contracts.

## Approved Phase 10A verification — 14 November 2026

NEW API submissions now reuse the unchanged public query before self/duplicate handling or any insertion/serialization. Focused security suite passed **18 tests / 316 assertions**; complete review suite passed **64 / 559**; full safe API/security regressions passed **385 / 3,377**, with no failures and one existing PHP 8.4 portable-ascii deprecation. Syntax/whitespace checks passed. Existing tests were changed only to provide public submission fixtures and reflect the approved restriction; old historical-review coverage was retained and expanded.

Tests cover pending/expired/past or NULL departure/inactive category/missing required joins, identical missing/nonpublic404 bodies, no private metadata or denied-request writes, duplicate/self checks occurring after visibility, malformed payload response equivalence, ratings0..5, today's public departure, no extra geography/account eligibility rules, existing public duplicates409 and existing history access after public eligibility is lost. No all-timing-inference, live browser, penetration or production concurrency claim is made.

Original25-table fingerprint and43 media hashes matched before/after. All1,991 protected existing file checksums matched, including PublicListingQuery, website controllers/templates, routes, Phase10B code/tests/docs, Phase10C-related files, environment and Git index. Actual route count remains37; listing DELETE409 and mobile reset revocation regressions passed. Only this document, CustomerReviewController.php and CustomerReviewTest.php changed. No original-data writes, migration, email, stage, commit, push or deployment. Historical metadata disclosure and legacy website review behavior remain explicitly outside this new-submission fix.

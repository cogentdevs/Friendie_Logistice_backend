# Listing requests and deals — Phase 4B

All routes use `/api/v1/customer`, `auth:sanctum` and the existing active, verified,
exclusively customer-role mobile authorization. Bearer tokens use `mobile:account`.
Generic deal responses contain no phone, email, address or exact coordinates.
The Phase 5A and Phase 6A sections below supersede the original lifecycle scope.
Phase 7B below adds persistent in-app notifications; earlier phase boundaries are historical.

| Method | Path | Input | Success |
| --- | --- | --- | --- |
| POST | `/deals` | `sender_listing_id`, `carrier_listing_id`, `requested_kg` | 201 |
| GET | `/deals` | Optional `page`, `per_page` (20 default, maximum 50) | 200 |
| GET | `/deals/{id}` | None | 200 |
| POST | `/deals/{id}/accept` | Empty | 200 |
| POST | `/deals/{id}/reject` | Empty | 200 |
| POST | `/deals/{id}/confirm` | Empty | 200 |
| POST | `/deals/{id}/cancel` | Empty | 200 |
| POST | `/deals/{id}/pickup` | Empty; carrier only | 200 |
| POST | `/deals/{id}/complete` | Empty; carrier only | 200 |

Example submission: `{"sender_listing_id":101,"carrier_listing_id":102,"requested_kg":5}`.
Unknown inputs, including participant identities and client-set statuses, return 422.
Only a participant can access a deal; missing/non-owned IDs return 404.
List results contain `data.items` and the existing `data.pagination` structure, newest ID first.

Responses use `success`, `message`, `data`. Deal data contains integer IDs for the deal,
both listings, both participants and initiator; requested/agreed KG; effective status;
lifecycle timestamps; `capacity.sender`, `capacity.carrier`; and caller-specific
`allowed_actions`. Capacity contains `total_kg`, `reserved_kg`, `committed_kg`,
`remaining_kg`, `available`. A deleted historical listing has null capacity.
Allowed actions are advisory; every mutation revalidates under locks.

## Lifecycle and capacity

Both listings must pass the existing public listing scope, have eligible customer owners,
have complementary Send/Carring types and identical directional country/city IDs.
They must belong to different customers. Either owner initiates; only the other participant
accepts/rejects; only the initiator confirms. Before acceptance only the initiator cancels.
Either participant can cancel an accepted or confirmed deal before recorded pickup.
Agreed KG equals requested KG and must equal the sender's entire positive whole-KG weight.
Carrier capacity can serve multiple confirmed deals; sender splitting is prohibited.

`pending -> accepted/rejected/cancelled`; `accepted -> confirmed/cancelled/expired`;
`confirmed -> cancelled` before pickup. Completed and pickup timestamps are reserved for
future phases, with no exposed actions to set them. Confirmed/completed commitments count
against capacity. There is no automatic cancellation on listing expiry.

Acceptance locks both listings for exactly 24 hours. It reserves KG without changing
`post_ads.weight` or `isActive`. Confirmation converts the reservation to commitment and
reopens eligible carrier capacity. Cancellation releases its reservation/commitment.
Remaining KG is original weight minus committed and unexpired reserved KG.
An acceptance hold blocks other submissions/acceptances for either listing even if spare
KG remains. Pending requests reserve nothing.

Expired holds are excluded immediately from GET availability without database writes.
GET exposes effective `expired`; a later deal mutation lazily persists expiry and releases
the unique pair slot. Authorized retries of the same completed action are idempotent and
never extend a hold or deduct twice. Other invalid transitions return 409.
Rejected/cancelled/expired pairs may submit a new record; history is retained.

All mutations lock the two listing rows in ascending ID order, then deal rows. The
nullable unique `active_pair` protects active-pair duplication. Capacity reads inside
transactions use locking reads; deadlocks retry up to three times. Account/IP mutation
limits are 60/minute. This is not a production load-test claim.

## Listing compatibility and deployment

Shared web/admin and API edits block material changes during live acceptance holds,
route/type changes with retained confirmed/completed obligations, weight reductions below
committed KG, and resizing a committed sender parcel. Material fields include route,
weight/price/currency, category, travel dates, status, areas, addresses and pins.
Title/description and existing image operations remain independent.
Web/admin hard-delete blocks pending, accepted-unexpired or confirmed obligations.
Terminal history survives deletion through stored minimal listing/route/owner snapshots;
there are no cascading foreign keys. API listing DELETE remains 409.

Before migration, existing listing operations keep working; deal routes return safe 503.
After a verified backup, the owner can manually execute ONLY this migration:

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_11_25_000001_create_listing_deals_table.php
```

The original database migration is not executed by this implementation. The isolated
database has the new table for focused tests. Never roll back a populated live deal table
casually: the migration's down method drops retained history.

Remaining boundaries: real-world pickup cannot be detected before a future pickup API;
confirmed cancellation currently relies on `picked_up_at` being null. No hold reminder,
notification worker, delivery completion or accepted-only contact access is included.
Unrelated legacy website authorization and Blade composer expiry behavior are unchanged.

## Listing availability responses — Phase 4D-1

The following responses add `availability` and `request_eligibility` to each listing,
without changing existing fields, visibility rules, listing status, filters or pagination:

- `GET /api/v1/listings` and `GET /api/v1/listings/{id}`.
- Both `featured_listings` and `latest_listings` in `GET /api/v1/home`.
- `GET /api/v1/customer/listings` and `GET /api/v1/customer/listings/{id}`.
- The full owner listing objects returned by listing POST and PUT.

Image-only response contracts and listing DELETE 409 are unchanged. Public feeds still
include published, nonexpired listings even when their capacity is currently unavailable.
Availability is a separate request/deal property, never an `isActive` update.

Exact added fields (a 12-KG carrier with 5 KG confirmed and a matching caller parcel):

```json
{
  "availability": {
    "total_kg": 12,
    "reserved_kg": 0,
    "committed_kg": 5,
    "remaining_kg": 7,
    "available": true
  },
  "request_eligibility": {
    "eligible": true,
    "reason": "eligible"
  }
}
```

KG fields are integers. `total_kg` uses the existing listing weight (null weight becomes
0 for calculations; the original `weight` field stays unchanged). Reserved KG sums only
unexpired accepted holds; committed KG sums confirmed/completed deals. Remaining KG is
`max(0, total - reserved - committed)`. Any live acceptance hold blocks availability even
when remaining KG is positive. Confirmation clears the hold and permits remaining carrier
capacity; cancellation releases its capacity. At the exact acceptance deadline the hold
is excluded without writing to the deal. Full parcels remain unsplit.

`available` requires positive remaining KG, no acceptance hold, the existing public scope
and an active, verified, exclusively customer-role listing owner. It describes the listing,
not whether this particular caller can submit a request.

`request_eligibility.eligible` is boolean for a valid bearer-token identity and null for
guests, invalid/revoked/expired tokens, or unknown infrastructure. Public browsing still
succeeds for those requests. A website session alone supplies no mobile identity. Valid
token context must satisfy the existing mobile ability/account rules; then a request needs
a different owner and at least one of the caller's available complementary listings with
matching From/To country/city IDs, adequate remaining carrier KG, a whole sender parcel and
no active request for that pair. Candidate listings and deal/participant details are never
included. Multiple candidates are considered; one eligible pair suffices.

| `reason` | Meaning |
| --- | --- |
| `eligible` | At least one pair can submit under current rules |
| `authentication_required` | No valid mobile bearer identity; eligible is null |
| `ineligible_account` | Valid token identity lacks existing customer/account/ability eligibility |
| `self_listing` | Caller owns the target listing |
| `listing_unavailable` | Target is unavailable, or was concurrently removed |
| `no_eligible_complementary_listing` | No currently submit-ready matching pair, including active-pair duplicates |
| `infrastructure_unavailable` | Deal table unavailable; capacity cannot be verified |

Guest example: `"request_eligibility":{"eligible":null,"reason":"authentication_required"}`.
A 5-KG live hold on a 12-KG carrier returns reserved=5, committed=0, remaining=7,
available=false. A fully committed 12-KG carrier returns reserved=0, committed=12,
remaining=0, available=false. Neither response reveals who reserved/booked it.

If the deal table is absent, browsing remains functional: `total_kg` is known but
`available`, `reserved_kg`, `committed_kg`, `remaining_kg` and caller eligibility are null.
No zero-capacity or available-capacity assumption is made. Concurrently deleted targets
likewise return unknown capacity in an already-fetched collection item.

Queries are batched across each returned collection (including both home sections), the
caller's candidate listings, owner roles and relevant active capacity records; query count
does not grow per returned listing. Large per-customer candidate/history sets can still
increase row processing. These are current read-time values, not reservations: submission
and transitions remain authoritative transactional checks and may return 409 after a race.
Personalized listing responses use `Cache-Control: no-store, private`. Optional public
bearer resolution does not update token `last_used_at`. No migration or mobile code is
required by this response extension; contact/pickup/delivery features remain out of scope.

## Accepted-deal contact and chat — Phase 5A

This section supersedes the earlier phase statements that contact access is not
implemented. No pickup, delivery, history UI, notifications or mobile integration is added.

**Manual deployment prerequisite:** one nullable unique BIGINT UNSIGNED
`listing_deals.conversation_id`, with no foreign key or cascade. The migration does not
backfill old rooms, rewrite descriptions, or associate historical conversations with deals.
After a verified backup, the owner may manually run:

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_11_25_000002_add_conversation_id_to_listing_deals_table.php
```

The command is not executed against the original database by this phase. Until the column
exists, private contact and chat creation/sending fail closed with 503; authorized legacy
history reads and listing owner access remain available. Rolling back this migration
removes authorization bindings, so do not discard populated live bindings casually.

### Private contact

`GET /api/v1/customer/deals/{id}/contact` takes no query/body parameters. It requires the
existing eligible mobile bearer customer and exact deal participation. Missing/unrelated
deal IDs return identical 404 errors. A participant receives 403 when the deal is not
confirmed or unexpired accepted, when either customer is ineligible, or when the stored
listing ownership/types/routes no longer establish the complementary relationship.

Example synthetic 200 response:

```json
{
  "success": true,
  "message": "Deal contact retrieved.",
  "data": {
    "deal_id": 51,
    "counterpart": {"id": 202, "phone": "+10000000002"},
    "listing": {
      "id": 102,
      "from_latitude": 24.8607343,
      "from_longitude": 67.0011364,
      "to_latitude": null,
      "to_longitude": null
    }
  }
}
```

The counterpart and listing are derived server-side: a sender receives the carrier's
phone and carrier-listing pins; a carrier receives the sender's phone and sender-listing
pins. No caller-selected user/listing override is accepted. Phone may be null. Missing,
invalid or incomplete coordinate pairs become two nulls; zero is a valid coordinate.
No email, address, arbitrary user model or other listing data is exposed. `show_contact_info`
does not grant access or replace deal authorization. Owner listing APIs keep their existing
access to the owner's own pins. Public listing/home responses and generic deal/chat
responses do not gain phone or exact coordinate fields. Responses are `no-store, private`.

### Dedicated conversations and existing chat endpoints

`POST /api/v1/customer/conversations` now requires only
`{"deal_id":51,"message":"Hello"}`. Listing-only creation and client participant IDs are
rejected with 422. The exact deal must authorize contact. The first open atomically creates
one NEW room, binds it to this deal, inserts the initial message and returns 201. Opening
the same deal again returns 200 with the same room and no duplicate initial message.
Another deal always creates another room, including later deals for the same listings and
participants. Existing room metadata adds nullable `deal_id`; deal list/details add nullable
`conversation_id`. Legacy unbound rooms report null and are never adopted automatically.

Existing conversation list/details/message-history/read-marker routes retain participant
scoping and response envelopes. The room uses the carrier listing as its anchor and stores
sender/carrier as its fixed two participants, regardless of who initiates the deal/chat.
`POST /customer/conversations/{id}/messages` still accepts only `message`; its exact stored
deal binding authorizes sending. Supplying another deal ID cannot reopen a closed room.
Text limits remain 10,000 characters / 65,535 UTF-8 bytes, nonempty, no NUL/attachments.
Sender/recipient identities are always server-derived. Existing chat mutation limits remain.

| Deal/room state | Private contact/pins | Create/open for writing | Send | Participant history |
| --- | --- | --- | --- | --- |
| Accepted, before its 24-hour deadline | Yes | Yes | Yes | Yes |
| Confirmed | Yes | Yes | Yes | Yes |
| Pending/rejected/cancelled/expired | No | No | No | Existing history only |
| Completed | No | No | No | Read-only |
| Unbound legacy room | No | No adoption | No | Existing authorized history only |

Acceptance expires at the exact deadline without requiring a worker or lazy state update.
Confirmed contact remains operational after the travel date/publication expires: capacity
and public publication were checked on acceptance and are not a new contact requirement.
Both customers must remain active, verified and exclusively customer-role; the current
listing pair must still match stored participants, complementary types and directional
country/city IDs. Completed bound history remains readable through the API after listing
deletion. Read markers remain idempotent recipient bookkeeping; read-only means no message
creation/edit/deletion. Previously read/copied content cannot be recalled.

Private reads and message writes lock both listing rows in ascending ID order, then the
deal and room as needed. Cancellation uses the same listing/deal locks: whichever operation
wins the transaction order determines whether disclosure/sending was authorized. A message
committed before cancellation remains historical; later sends fail. API operations send no
mail/notifications and do not create or change acceptance, confirmation or delivery state.

### Minimum website safeguards

The existing chat controllers use the same lifecycle/room gate for creation and sending,
derive participants server-side, reject spoofed IDs, and authorize room/page/polling reads
before message access or read-state updates. Existing routes and layouts are unchanged.
An old web create form can resolve an unambiguous active deal for its listing; if multiple
deals match it returns 409 and requires explicit `deal_id` rather than choosing a room.
Existing room links still identify their own exact deal. Authorized website chat keeps its
existing email behavior; no new mail workflow is introduced.

The website listing Contact Info card is now owner-only (and retains its existing display
flag), preventing guest/logged-in non-owner phone/email leakage. Counterpart private phone
is exposed only by the authorized deal-scoped API above. No CSS/JS, form fields, navigation
or layouts were redesigned. Legacy unbound chats remain read-only; website listing
grouping/navigation for deleted parent listings remains its existing presentation limit.

## Phase 6A: pickup, delivery completion and authenticated history

This section supersedes earlier statements reserving delivery actions for a future phase.
No schema change, migration or historical record rewrite is required. Existing
`picked_up_at`, `completed_at`, `listing_snapshot` and `completed` status are reused.

All paths below use `/api/v1/customer`, eligible customer Sanctum authentication and
`mobile:account`. Mutation rate limits remain 60 attempts per user/IP per minute.

| Method | Path | Request | Result |
| --- | --- | --- | --- |
| POST | `/deals/{id}/pickup` | Empty JSON `{}` | Carrier only; confirmed deal records pickup and remains confirmed |
| POST | `/deals/{id}/complete` | Empty JSON `{}` | Carrier only; confirmed deal with pickup becomes completed |
| GET | `/deals` | Optional `status=pending` or `status=completed`, `page`, `per_page` | Current participant's history |
| GET | `/deals/{id}` | None | Current participant's deal, history and allowed actions |

Success uses the existing `success`, `message`, `data` envelope, HTTP 200. Other participants
cannot access a deal (404); the sender cannot invoke pickup/completion (403). Missing IDs
return 404, invalid lifecycle transitions 409, unsupported input 422, guest 401 and
ineligible account 403. Existing infrastructure failure handling is unchanged.

Pickup requires confirmation. Completion requires recorded pickup; no direct pending,
accepted or cancelled-to-completed transition is allowed. Cancellation after pickup is
409 for either participant. Only the stored carrier may invoke either delivery action,
regardless of who initiated the original request. Publication/date expiry does not block
an already confirmed journey's pickup or completion. Stored participant IDs remain the
authority when a parent listing is missing.

Authorized retries preserve the original timestamps and cannot consume capacity twice:
pickup retries succeed after pickup (including after completion), and completion retries
succeed after completion. Both actions lock existing listing rows by ascending ID, then
the scoped deal row, in the same transaction/lock order as cancellation, capacity and
contact actions. Storage failure rolls back; deadlocks use the existing three retries.
Completed KG remains committed on both listings for the same trip. No listing weight or
`isActive` value is changed. Completion clears `active_pair`; this does not free capacity
or make the committed sender parcel eligible for another deal.

For confirmed deals, `allowed_actions` is `['cancel','pickup']` for the carrier before
pickup and `['cancel']` for the sender. After pickup it is `['complete']` for the carrier
and `[]` for the sender. Completed deals return `[]`. The existing timestamps include
`picked_up_at`, `completed_at`, `confirmed_at`, acceptance/cancellation/rejection dates,
`created_at` and `updated_at`; all retain their existing nullable date-time format.

History is returned through the existing list (`data.items`, `data.pagination`) and
detail responses; no separate duplicate history endpoint. `per_page` defaults to 20,
maximum 50, ordered by descending immutable deal ID. No filter returns all states.
`status=pending` means unfinished actionable work: pending requests, live accepted holds
and confirmed deals (including picked-up trips). It excludes expired holds, rejected,
cancelled and completed deals. `status=completed` returns only completed deals. GET never
persists lazy expiry or modifies a deal, read receipt, token or listing.

Every deal response adds this explicit snapshot allowlist (synthetic excerpt):

```json
{"history":{"sender":{"user":{"id":201,"display_name":"Example Sender"},"listing":{"id":101,"title":"Parcel","ad_type":"Send","weight_kg":5,"route":{"from_country_id":1,"from_city_id":2,"to_country_id":3,"to_city_id":4}}},"carrier":{"user":{"id":202,"display_name":"Example Carrier"},"listing":{"id":102,"title":"Parcel capacity","ad_type":"Carring","weight_kg":12,"route":{"from_country_id":1,"from_city_id":2,"to_country_id":3,"to_city_id":4}}}}}
```

The existing top-level `agreed_kg` is the parcel's committed weight; snapshot `weight_kg`
is each listing's original total. Snapshot fields are fixed at request submission, not
reconstructed from later edited listings. New requests additionally snapshot safe title
and participant display name. Existing records are not backfilled: unrecorded fields
are null, including participant names/titles absent in older snapshots. Route IDs are
preserved; the Phase 6C-1 section below defines additional readable route labels.
Display text uses the existing contact-filtering
plain-text serializer and may be null. No phone, email, street address, exact coordinates
or raw snapshot JSON is exposed by history. Live capacity is null when a listing is gone;
retained snapshots, participant IDs, agreed weight and timestamps remain accessible.

Completion immediately denies private contact/coordinate access and new conversation or
message creation under Phase 5A rules. Existing bound conversation history stays readable
to its participants; read receipts remain permitted. Owner access to their own coordinates
is unchanged. No notifications, reviews, mobile UI or website/admin changes are included.

Postman examples: `POST {{base_url}}/customer/deals/51/pickup`, then
`POST {{base_url}}/customer/deals/51/complete`, both with `{}` and carrier bearer token.
List completed history with `GET {{base_url}}/customer/deals?status=completed&page=1&per_page=20`.

## Phase 6C-1: readable history route names

Deal list/history and detail responses add four nullable strings to each
`history.sender.listing.route` and `history.carrier.listing.route`:
`from_country_name`, `from_city_name`, `to_country_name`, `to_city_name`.
The same serializer is used by submission and lifecycle action responses. Existing IDs,
envelopes, pagination, status filters and authorization remain unchanged. Example:

```json
{"from_country_id":1,"from_city_id":2,"to_country_id":3,"to_city_id":4,"from_country_name":"Alpha","from_city_name":"Alpha City","to_country_name":"Beta","to_city_name":"Beta City"}
```

New deal submissions record these labels alongside the existing route IDs in each
sender/carrier snapshot. Recorded names take precedence over later reference-table or
listing edits, expiry and permitted listing deletion. Explicit recorded nulls remain null.
For older snapshots missing a name key, the API resolves the corresponding **snapshot
route ID** against the existing countries/cities `name` column. This fallback is the
current reference label, not proof of its historical spelling. It does not read a later
edited listing's route or invent a name from another field. Missing IDs/rows, empty names
or names withheld by the existing safe-text privacy filter return null. Inactive reference
rows can still identify historical routes; this does not change new-deal eligibility.

Reference name lookups are batched across the authorized history page: at most one
countries query and one cities query, independent of its item count. No per-deal route
name queries are added. Fully recorded snapshots need no name lookup. Existing unrelated
capacity/eligibility query behavior is unchanged. GET performs no snapshot backfill or
data rewriting. No private phone, email, address or exact coordinates are added.

## Phase 7B: persistent in-app deal notifications

Requires the approved `deal_notifications` table. No website/admin, push, email,
broadcasting or mobile changes are included. Existing dashboard chat unread counts are
unchanged; notification counts use their own endpoint. Historical deals are not backfilled.

All paths below require the existing eligible customer Sanctum bearer authentication:

| Method | Path under `/api/v1/customer` | Input | Success |
| --- | --- | --- | --- |
| GET | `/notifications` | Optional `filter=all|tasks|unread`, `page`, `per_page` | 200; `data.items`, `data.pagination` |
| GET | `/notifications/counts` | None | 200; `data.unread_count`, `data.pending_task_count` |
| POST | `/notifications/{id}/read` | Empty JSON `{}` | 200; notification with resulting read/visibility state |

The feed defaults to all **visible** notifications, newest ID first, 20 per page, maximum
50. `tasks` includes active tasks whether read or unread; `unread` includes unread visible
tasks and informational events. `all` does not expose hidden notification rows.

Notification responses explicitly allow: `id`, `event`, `deal_id`, effective `deal_status`,
`is_read`, nullable `read_at`, `created_at`, `is_pending_task`, `visible`, `allowed_actions`
and `summary`. The summary contains `parcel_title`, `weight_kg`, route country/city IDs
and names, and sender/carrier `{id,display_name}`. It uses retained snapshots only;
unrecorded names are null, text passes the existing privacy filter, and deleted parent
listings do not erase it. There are no phone/email/address/exact-coordinate fields, raw
snapshots or unrestricted models. Generic deal/history APIs remain unchanged.

Synthetic counts response:

```json
{"success":true,"message":"Notification counts retrieved.","data":{"unread_count":2,"pending_task_count":1}}
```

| Event value | Recipient | Active task condition / lifecycle actions |
| --- | --- | --- |
| `request_submitted` | Non-initiating participant | Deal pending: accept/reject |
| `request_accepted` | Initiator | Live accepted hold: confirm/cancel |
| `request_rejected` | Initiator | Informational |
| `deal_confirmed` | Both | Carrier before pickup: cancel/pickup; sender informational |
| `deal_cancelled` | Non-cancelling participant | Informational |
| `parcel_picked_up` | Both | Carrier on confirmed picked-up trip: complete; sender informational |
| `delivery_completed` | Both | Informational |

Actions describe the outstanding lifecycle step, not a capacity reservation or promise
of mutation success. Existing deal endpoints still enforce current capacity, account,
listing and transition requirements and can return their existing conflicts. Notifications
never grant contact access or bypass deal authorization.

Active tasks remain visible after reading. On resolution, an already-read task disappears;
an unread resolved task remains informational without actions until viewed. Informational
and delivery-completed events disappear on reading. Hiding never deletes notification or
deal records. Read retries preserve the original `read_at`; hidden rows can be marked
read again by their own recipient. `unread_count` counts unread visible rows;
`pending_task_count` includes active tasks even after reading. Counts/feed reflect current
deal state and may change between requests due to concurrent actions.

Acceptance expiry resolves tasks using the server clock, including exactly at the
24-hour boundary, without GET writes, a scheduler or an extra expiry notification. GET
does not update read state, deals or Sanctum token usage. Successful responses are `no-store,
private`. Recipient identity is derived from authentication, and the joined deal must
also identify that user as a participant. Missing and non-owned notification IDs return
the same 404. Guests get 401, ineligible accounts 403, invalid fields/filters/pagination
422. Mark-read is limited to 120 attempts per user/IP per minute (429 with Retry-After).

Notification creation occurs in the **same transaction** as actual request creation or
deal transitions. Authorized lifecycle retries create no additional events. The unique
`(deal_id,event,recipient_user_id)` index provides final duplicate protection. A notification
storage failure rolls back the deal action and capacity changes. Read mutations lock the
deal before its notification, consistent with lifecycle locking, and never change deal
state. Joined feed/count queries avoid per-notification lookups.

Deployment adds only `deal_notifications`: unsigned BIGINT `id`, `recipient_user_id`,
`deal_id`; VARCHAR(32) `event`; nullable TIMESTAMP `read_at`; TIMESTAMP `created_at`.
Indexes are the unique event/recipient key, `(recipient_user_id,id)` and
`(recipient_user_id,read_at,id)`. No foreign-key cascade, existing-column change,
historical backfill or new dependency is introduced.

After verifying a backup and database connection, the owner must manually run ONLY:

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_11_25_000003_create_deal_notifications_table.php
```

This migration was not executed against the original database. Until the table is
deployed, notification APIs return 503, and actual new deal mutations roll back with 503
rather than silently losing notifications. Read-only deal/history access is unaffected.
Deploy the schema before enabling the updated lifecycle code. Migration rollback drops
notification/read history and must not be run blindly against a populated table.

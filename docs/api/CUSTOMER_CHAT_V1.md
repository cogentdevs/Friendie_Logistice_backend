# Friendie Logistics — Customer Chat API v1

**Phase 5A update:** the historical Phase 7 creation/send rules below are superseded by
[Listing deals — accepted-deal contact and chat](LISTING_DEALS_V1.md#accepted-deal-contact-and-chat--phase-5a).
Conversation creation now requires `deal_id` and `message`; listing-only creation is rejected.
Only that dedicated conversation's unexpired accepted/confirmed deal authorizes sending.
Closed/completed and unbound legacy rooms are read-only. Phone/pins are returned only by
`GET /api/v1/customer/deals/{id}/contact`, never these generic chat responses.

Historical Phase 7 contract follows. All examples are synthetic.

## Verified existing model

- `post_ad_rooms`: BIGINT primary `id`, required integer `post_ads_id`, `from_user_id`, `to_user_id`, nullable timestamps. Website creation sets initiator as from-user and listing owner as to-user.
- `post_ad_room_messages`: BIGINT primary `id`, required integer room/from/to IDs, TEXT `message`, VARCHAR `message_on`, nullable VARCHAR `read_by`, nullable timestamps.
- Website writes recipient user ID directly into `read_by`; NULL means unread. It is not a JSON array or multi-reader list.
- `post_ads.created_by` identifies owner. Models have no declared relationships. There are no chat foreign keys, duplicate-room unique constraints, acceptance records, attachments or message-type fields.
- Existing web controller sends chat email and performs a read update during GET. These side effects are not called by the API. Existing website code remains unchanged. API requests intentionally send no mail/notification and GETs do not mark read or touch token last-used state.

## Headers and authorization

Base URL: `{{base_url}} = https://api.example.test/api/v1` (replace with trusted reachable server).

```text
Accept: application/json
Authorization: Bearer {{access_token}}
Content-Type: application/json
```

Use existing Phase 4 login. Every route requires `auth:sanctum`, eligible active/verified exclusively customer role, and mobile account ability. GETs use the existing read-only Sanctum middleware. Missing/invalid/revoked token 401; ineligible caller 403. No web session substitute.

Room authorization requires the caller to equal one of the two stored distinct participant IDs, both user records to exist, listing to exist, and `room.to_user_id = listing.created_by`. Missing, malformed, orphaned, structurally inconsistent and unauthorized rooms return the same 404. Existing history is not hidden merely because listing is now pending/expired. Account eligibility for callers is always enforced; the other participant's later disablement does not delete history.

## Common responses

Success: `success:true,message,data`. Field validation: 422 `success:false,message:"Validation failed",errors`. Unsupported fields are rejected. Rate limit 429 with Retry-After; unexpected error sanitized 500. No SQL/stack trace output. Customer GETs have private/no-store responses.

All mutation endpoints share **60 attempts/minute per account and per IP**, including failures. No attachments, sender IDs, recipient IDs, role/status flags or client timestamps are accepted.

## 1. GET /customer/conversations

Optional query: `page` (1..100000), `per_page` (1..50; default 20). No search or arbitrary user filter.

```json
{"success":true,"message":"Conversations retrieved successfully.","data":{"items":[{"id":1,"listing":{"id":10,"title":"Parcel capacity"},"other_participant":{"id":2,"display_name":"Example Owner"},"created_at":"2026-11-14 12:00:00","last_message":"Hello","last_message_at":"2026-11-14 12:00:00","unread_count":1}],"pagination":{"current_page":1,"per_page":20,"total":1,"last_page":1}}}
```

Order: latest valid message ID descending, room ID descending. IDs represent persisted insertion activity and avoid ambiguous legacy NULL/tied timestamps. Empty rooms sort last; previews/timestamps are null. Last-message and unread results are batched, avoiding per-room queries. Invalid legacy messages whose sender/recipient pair does not match the room are excluded from previews, history and counts. Empty results are items `[]`.

## 2. GET /customer/conversations/{id}

No query/body. Returns only metadata: `id`, `listing.id/title`, `other_participant.id/display_name`, `created_at` in standard success data. Does not mark read. No private listing areas, account email/phone or status/acceptance proof.

## 3. GET /customer/conversations/{id}/messages

Optional page/per_page as above. Authorize conversation first. Order is message ID ascending (oldest persisted first), stable even when legacy timestamps are missing/equal.

```json
{"success":true,"message":"Messages retrieved successfully.","data":{"items":[{"id":1,"conversation_id":1,"sender":{"id":2,"display_name":"Example Owner"},"message":"Hello","created_at":"2026-11-14 12:00:00","message_on":"14 Nov 2026","is_read":false}],"pagination":{"current_page":1,"per_page":20,"total":1,"last_page":1}}}
```

`is_read` is true only if stored read_by exactly equals that message's recipient ID. Legacy non-null unexpected values are preserved, not interpreted as valid recipient proof. Unread counts follow existing website semantics: read_by IS NULL. There is no reliable read timestamp. Offset pagination is not a realtime delivery/cursor guarantee under concurrent insertion.

## 4. POST /customer/conversations

```json
{"post_ads_id":10,"message":"Hello, I would like to discuss this listing."}
```

Both fields required. Listing ID positive integer, maximum 2147483647 matching room's existing INT reference. Message validation below. No client participant IDs.

Derives initiator from authentication and recipient from listing.created_by; self-chat rejected 422. Target owner must currently be active, verified and exclusively customer role; missing/ineligible targets 404.

New rooms require existing public listing eligibility plus departure strictly after today, consistent with the website Message Now date gate. The existing public query is reused without modification. Pending/private/expired/ineligible listing cannot be targeted by guessed ID. Existing matching rooms can still be reused for history after listing expiry.

Customer and listing are locked transactionally. Reuse the earliest existing exact listing/initiator/owner room: return 200 `Conversation already exists.` with metadata and **do not append the submitted message again**. A new room and first message commit atomically: 201 `Conversation created successfully.` with metadata. Subsequent messages use the send endpoint. Message failure rolls back the newly created room.

API creates serialize on the listing lock. There is no unique index and legacy website creation does not participate in this protocol: **global duplicate prevention against simultaneous website writes cannot be guaranteed**. Existing duplicate rooms are not merged/deleted. No migration or website change is introduced.

## 5. POST /customer/conversations/{id}/messages

```json
{"message":"Thank you for the details."}
```

Required nonempty text, trimmed by existing Laravel request handling, maximum 10,000 characters and 65,535 encoded bytes; NUL rejected. Website has no explicit length maximum; the API applies this documented safe input bound inside the existing TEXT capacity. Arrays, empty/whitespace-only values, unknown fields, attachments and sender/recipient impersonation return 422.

Sender is authenticated caller; recipient is the other room participant. Transaction rechecks account/token and locks room. Stores existing `message_on` format `d M Y`, timestamps and NULL read_by. Returns 201 `Message sent successfully.` with the same message object used in history. No automatic acceptance, no email/notification, no read side effect. Existing conversations can continue after listing expiry. Other participant account enablement is not changed.

Message strings are private participant-authored content, returned as plain text, never trusted HTML; render with text components. Participant profile phone/email are never queried/returned. User-entered message content can itself contain volunteered contact information; this is not an account-contact lookup or an accepted relationship. Semantic content moderation is not implemented.

## 6. POST /customer/conversations/{id}/read

Empty body. Transactionally updates only valid room messages addressed to caller with NULL read_by; sets existing scalar string user ID and updated_at. Does not overwrite any existing reader value or mark caller's outgoing messages. Repeated calls return zero newly marked messages.

```json
{"success":true,"message":"Messages marked as read.","data":{"conversation_id":1,"marked_read":2,"unread_count":0}}
```

Unread count is accurate for the transaction snapshot; a later new message may make it positive again. New API sends/reads use room locking. Legacy website writers do not provide the same concurrency guarantees. No read receipts timestamp, multiple-reader encoding or background job is invented.

## Postman sequence

1. On an isolated backend with synthetic accounts and configured token table, log in as an eligible customer; privately save token.
2. Select another eligible customer's future public listing. POST the listing ID and first message; save conversation ID.
3. GET conversation list/details/history, send another message, then use the other synthetic participant's token to read and reply.
4. POST read and repeat: first call reports newly marked messages, second zero. GET alone never marks read.
5. Attempt unrelated room IDs and impersonation fields: expect 404 and 422 respectively. Do not export live tokens/screenshots with private messages.

## Unsupported features and preserved scope

No accepted requests, contact reveal, websocket/push/poll worker, typing indicators, attachments, reactions, message edit/delete, group chat, account status changes, archive, soft delete or new review APIs. Chat existence never grants another account's phone/email. Phase 6 listing DELETE remains 409. Reference APIs remain complete nonpaginated matching lists.

Original database remains untouched. Its separately approved Sanctum token-table installation is still a prerequisite for real mobile bearer-token usage. Existing unsafe website sender/participant authorization, duplicate-room behavior and wildcard Blade expiry writes are separate unresolved issues, not fixed here. Old unsafe web writers can still affect shared data; API filtering rejects structurally inconsistent room/message records but cannot prove legacy participant provenance or repair it.

## Verification record — 14 November 2026

- Restricted test-database identity, privilege restrictions and isolated filesystem/mail safeguards passed before implementation.
- Focused chat suite: **25 tests / 217 assertions**. Combined selected chat, foundation, public, reference, authentication, security, profile and listing suites: **309 tests / 2,712 assertions**. No failed tests; one pre-existing PHP 8.4 vendor portable-ascii deprecation. Following an attempt-state reset for transaction retries, the focused suite passed again with the same counts.
- Coverage includes all six routes' guest/invalid/revoked/ineligible caller handling, ownership/participant IDOR, malformed rooms/messages, safe response fields, pagination, latest persisted activity, NULL-reader semantics, idempotent read updates, existing-reader preservation, duplicate reuse, message validation/injection, transactional first-message failure, rate limiting, no GET writes/view rendering, no outbound mail and previous API regressions.
- Transaction locking/deadlock retries are implemented; no claim is made of a simultaneous real-browser/API load test or globally unique rooms against legacy web writers. No production load benchmark was run. Existing absent chat secondary indexes can make large-dataset correlated activity/unread queries expensive; no index or schema change is introduced.
- Original 25-table schema/data fingerprint matched before/after; all 43 baseline media hashes matched. Checksums of all 1,981 pre-existing protected files (including environment, dependencies, Git index and the pre-existing Word document) matched, excluding only the deliberately extended API route and route-inventory files.
- Original database has no personal_access_tokens table. No migration was executed. Current Git HEAD at inspection: `495fe4b7c753491504654d626b039a0416a9edbb`; no commit, stage, push, deployment or next-phase implementation performed. The old 28-endpoint Word/public overview is preserved unchanged; adding six routes yields 34 registered API routes, with listing DELETE still blocked.

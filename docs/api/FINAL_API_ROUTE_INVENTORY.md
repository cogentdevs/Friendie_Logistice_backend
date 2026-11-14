# Final API Route Inventory v1

Audited 14 November 2026 using the actual Laravel router booted exclusively through verified isolated test configuration. Laravel 11.27.2, PHP CLI 8.4.24. HEAD `495fe4b7c753491504654d626b039a0416a9edbb` plus preserved uncommitted Phase 7/8 routes.

**37 route entries / primary HTTP operations: 13 public and 24 protected.** GET also registers HEAD; HEAD aliases are not separate business endpoints. 36 implemented operations plus one deliberately blocked listing DELETE. No duplicate method/path or route collisions found; shared paths with different methods are intentional. All target Phases 2–8 routes exist. No optional review-details or mobile verify-email API exists.

## Middleware legend

- P: `api` only; guest accessible. API group prepends EnsureApiJson. No auth requirement.
- C: `api, auth:sanctum, EligibleMobileCustomer`; valid personal token with mobile:account plus currently active/verified exclusively user role.
- R: `api, ReadOnlyCustomerSanctum, auth:sanctum, EligibleMobileCustomer`; C plus GET authentication without token last_used_at writes.
- auth/me uses C, not R; standard Sanctum can update token last_used_at. No session/CSRF cookie is needed for APIs; web CSRF remains enabled.

All data uses `success,message,data`; validation 422 uses errors. Unexpected failures sanitized500. GET list responses use `data.items,data.pagination{current_page,per_page,total,last_page}` except four reference lists, which have only items. IDs shown in Postman are synthetic placeholders, not authorization.

## Registered routes

| # | Method | Full path | Controller/action | Middleware | Status |
|---|---|---|---|---|---|
| 1 | GET (+HEAD) | `/api/v1/health` | `HealthController` | P | IMPLEMENTED / isolated-tested |
| 2 | GET (+HEAD) | `/api/v1/home` | `HomeController` | P | IMPLEMENTED / isolated-tested |
| 3 | GET (+HEAD) | `/api/v1/categories` | `CategoryController` | P | IMPLEMENTED / isolated-tested |
| 4 | GET (+HEAD) | `/api/v1/countries` | `CountryController` | P | IMPLEMENTED / isolated-tested |
| 5 | GET (+HEAD) | `/api/v1/cities` | `CityController` | P | IMPLEMENTED / isolated-tested |
| 6 | GET (+HEAD) | `/api/v1/currencies` | `CurrencyController` | P | IMPLEMENTED / isolated-tested |
| 7 | GET (+HEAD) | `/api/v1/listings` | `ListingController@index` | P | IMPLEMENTED / isolated-tested |
| 8 | GET (+HEAD) | `/api/v1/listings/{id}` | `ListingController@show` | P | IMPLEMENTED / isolated-tested |
| 9 | POST | `/api/v1/auth/register` | `AuthController@register` | P | IMPLEMENTED / isolated-tested |
| 10 | POST | `/api/v1/auth/login` | `AuthController@login` | P | IMPLEMENTED / isolated-tested |
| 11 | POST | `/api/v1/auth/forgot-password` | `AuthController@forgotPassword` | P | IMPLEMENTED / isolated-tested |
| 12 | POST | `/api/v1/auth/reset-password` | `AuthController@resetPassword` | P | IMPLEMENTED / isolated-tested |
| 13 | POST | `/api/v1/auth/resend-verification` | `AuthController@resendVerification` | P | IMPLEMENTED / isolated-tested |
| 14 | GET (+HEAD) | `/api/v1/auth/me` | `AuthController@me` | C | IMPLEMENTED / isolated-tested |
| 15 | POST | `/api/v1/auth/logout` | `AuthController@logout` | C | IMPLEMENTED / isolated-tested |
| 16 | GET (+HEAD) | `/api/v1/customer/dashboard` | `CustomerController@dashboard` | R | IMPLEMENTED / isolated-tested |
| 17 | GET (+HEAD) | `/api/v1/customer/profile` | `CustomerController@profile` | R | IMPLEMENTED / isolated-tested |
| 18 | PUT | `/api/v1/customer/profile` | `CustomerController@updateProfile` | C | IMPLEMENTED / isolated-tested |
| 19 | POST | `/api/v1/customer/profile/avatar` | `CustomerController@uploadAvatar` | C | IMPLEMENTED / isolated-tested |
| 20 | DELETE | `/api/v1/customer/profile/avatar` | `CustomerController@removeAvatar` | C | IMPLEMENTED / isolated-tested |
| 21 | POST | `/api/v1/customer/change-password` | `CustomerController@changePassword` | C | IMPLEMENTED / isolated-tested |
| 22 | GET (+HEAD) | `/api/v1/customer/listings` | `CustomerListingController@index` | R | IMPLEMENTED / isolated-tested |
| 23 | POST | `/api/v1/customer/listings` | `CustomerListingController@store` | C | IMPLEMENTED / isolated-tested |
| 24 | GET (+HEAD) | `/api/v1/customer/listings/{id}` | `CustomerListingController@show` | R | IMPLEMENTED / isolated-tested |
| 25 | PUT | `/api/v1/customer/listings/{id}` | `CustomerListingController@update` | C | IMPLEMENTED / isolated-tested |
| 26 | DELETE | `/api/v1/customer/listings/{id}` | `CustomerListingController@destroy` | C | BLOCKED 409 |
| 27 | POST | `/api/v1/customer/listings/{id}/image` | `CustomerListingController@uploadImage` | C | IMPLEMENTED / isolated-tested |
| 28 | DELETE | `/api/v1/customer/listings/{id}/image` | `CustomerListingController@removeImage` | C | IMPLEMENTED / isolated-tested |
| 29 | GET (+HEAD) | `/api/v1/customer/conversations` | `CustomerChatController@index` | R | IMPLEMENTED / isolated-tested |
| 30 | GET (+HEAD) | `/api/v1/customer/conversations/{id}` | `CustomerChatController@show` | R | IMPLEMENTED / isolated-tested |
| 31 | GET (+HEAD) | `/api/v1/customer/conversations/{id}/messages` | `CustomerChatController@messages` | R | IMPLEMENTED / isolated-tested |
| 32 | POST | `/api/v1/customer/conversations` | `CustomerChatController@store` | C | IMPLEMENTED / isolated-tested |
| 33 | POST | `/api/v1/customer/conversations/{id}/messages` | `CustomerChatController@send` | C | IMPLEMENTED / isolated-tested |
| 34 | POST | `/api/v1/customer/conversations/{id}/read` | `CustomerChatController@read` | C | IMPLEMENTED / isolated-tested |
| 35 | GET (+HEAD) | `/api/v1/customer/reviews/received` | `CustomerReviewController@received` | R | IMPLEMENTED / isolated-tested |
| 36 | GET (+HEAD) | `/api/v1/customer/reviews/given` | `CustomerReviewController@given` | R | IMPLEMENTED / isolated-tested |
| 37 | POST | `/api/v1/customer/reviews` | `CustomerReviewController@store` | C | IMPLEMENTED / isolated-tested |

## Endpoint contracts, validation and restrictions

### 1. GET /api/v1/health

- Authentication: Public.
- Input: No body.
- Optional query: none.
- Response shape: data.status, api_version.
- Expected statuses: 200; infrastructure exceptions return sanitized 500.
- Rules: No business parameters. HealthController does not validate/reject extra query parameters; they are ignored.

### 2. GET /api/v1/home

- Authentication: Public.
- Input: No body.
- Optional query: none.
- Response shape: data.categories, featured_listings, latest_listings.
- Expected statuses: 200,422; infrastructure exceptions return sanitized 500.
- Rules: Limits 12/8/8; published/nonexpired query; featured departure > today.

### 3. GET /api/v1/categories

- Authentication: Public.
- Input: No body.
- Optional query: `search`.
- Response shape: data.items: id,name,image_url; no pagination.
- Expected statuses: 200,422; infrastructure exceptions return sanitized 500.
- Rules: All eligible matching records; literal case-insensitive search; exact, prefix, substring ranking.

### 4. GET /api/v1/countries

- Authentication: Public.
- Input: No body.
- Optional query: `search`.
- Response shape: data.items: id,name,short_name,image_url; no pagination.
- Expected statuses: 200,422; infrastructure exceptions return sanitized 500.
- Rules: All eligible matching records; literal case-insensitive search; exact, prefix, substring ranking.

### 5. GET /api/v1/cities

- Authentication: Public.
- Input: No body.
- Optional query: `country_id`, `search`.
- Response shape: data.items: id,country_id,name,short_name; no pagination.
- Expected statuses: 200,422; infrastructure exceptions return sanitized 500.
- Rules: All eligible matching records; literal case-insensitive search; exact, prefix, substring ranking.

### 6. GET /api/v1/currencies

- Authentication: Public.
- Input: No body.
- Optional query: `search`.
- Response shape: data.items: code; no pagination.
- Expected statuses: 200,422; infrastructure exceptions return sanitized 500.
- Rules: All eligible matching records; literal case-insensitive search; exact, prefix, substring ranking.

### 7. GET /api/v1/listings

- Authentication: Public.
- Input: No body.
- Optional query: `search`, `category_id`, `from_country_id`, `to_country_id`, `from_city_id`, `to_city_id`, `ad_type`, `weight`, `departure_on`, `sort`, `page`, `per_page`.
- Response shape: data.items + data.pagination; safe listing cards.
- Expected statuses: 200,422; infrastructure exceptions return sanitized 500.
- Rules: 20 default / 50 maximum; sorts latest, oldest, departure; published, active category, departure >= today.

### 8. GET /api/v1/listings/{id}

- Authentication: Public.
- Input: No body.
- Optional query: none.
- Response shape: data: safe listing card + description.
- Expected statuses: 200,404,422; infrastructure exceptions return sanitized 500.
- Rules: Same public eligibility; no private areas, owner email/phone.

### 9. POST /api/v1/auth/register

- Authentication: Public.
- Input: JSON: `name`, `email`, `phone`, `country_id`, `password`, `password_confirmation`, `captcha_token`.
- Optional query: none.
- Response shape: data:{}; neutral confirmation; no token.
- Expected statuses: 201,422,429; infrastructure exceptions return sanitized 500.
- Rules: All fields required; CAPTCHA required; duplicate email/phone neutral; 5/IP/hour.

### 10. POST /api/v1/auth/login

- Authentication: Public.
- Input: JSON: `email`, `password`.
- Optional query: none.
- Response shape: data.token,token_type,user{id,name,email}.
- Expected statuses: 200,401,422,429,500; infrastructure exceptions return sanitized 500.
- Rules: Only eligible customer; 5/email/15min,20/IP/15min; token table absent on original DB.

### 11. POST /api/v1/auth/forgot-password

- Authentication: Public.
- Input: JSON: `email`.
- Optional query: none.
- Response shape: data:{}; neutral mail confirmation.
- Expected statuses: 200,422,429; infrastructure exceptions return sanitized 500.
- Rules: 60-minute reset; hashed role/account/current-email proof; 60s cooldown.

### 12. POST /api/v1/auth/reset-password

- Authentication: Public.
- Input: JSON: `user_id`, `token`, `password`, `password_confirmation`.
- Optional query: none.
- Response shape: data:{}; no auto login.
- Expected statuses: 200,422,429; infrastructure exceptions return sanitized 500.
- Rules: ID requires emailed proof; atomic single use; existing mobile tokens NOT revoked.

### 13. POST /api/v1/auth/resend-verification

- Authentication: Public.
- Input: JSON: `email`.
- Optional query: none.
- Response shape: data:{}; neutral mail confirmation.
- Expected statuses: 200,422,429; infrastructure exceptions return sanitized 500.
- Rules: Active unverified customer only; 24h signed browser link; 60s cooldown; prior links not revoked.

### 14. GET /api/v1/auth/me

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: none.
- Response shape: data.id,name,email.
- Expected statuses: 200,401,403; infrastructure exceptions return sanitized 500.
- Rules: Standard Sanctum writes token last_used_at; no arbitrary account selector.

### 15. POST /api/v1/auth/logout

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: Empty body.
- Optional query: none.
- Response shape: data:{}.
- Expected statuses: 200,401,403; infrastructure exceptions return sanitized 500.
- Rules: Revoke current token only; web sessions/other devices preserved.

### 16. GET /api/v1/customer/dashboard

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: none.
- Response shape: data.listings{total,published,pending,expired},unread_messages,reviews_received.
- Expected statuses: 200,401,403,422; infrastructure exceptions return sanitized 500.
- Rules: Stored-status counts, not computed public visibility.

### 17. GET /api/v1/customer/profile

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: none.
- Response shape: data.id,name,email,phone,country_id,country,image_url,is_verified,email_verified_at.
- Expected statuses: 200,401,403,422; infrastructure exceptions return sanitized 500.
- Rules: Own email/phone only.

### 18. PUT /api/v1/customer/profile

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: JSON: `name`.
- Optional query: none.
- Response shape: data.profile,verification_required,reauthentication_required.
- Expected statuses: 200,401,403,422,429; infrastructure exceptions return sanitized 500.
- Rules: Optional supported fields name,email,phone,country_id; at least one. Email change clears verification/revokes all account tokens.

### 19. POST /api/v1/customer/profile/avatar

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: multipart/form-data File field **avatar**; do not set JSON Content-Type.
- Optional query: none.
- Response shape: data.image_url.
- Expected statuses: 200,401,403,422,429,500; infrastructure exceptions return sanitized 500.
- Rules: multipart avatar (NOT image). JPEG/PNG/WebP <=2048KiB, dimensions <=2048 each; reencode; old files retained.

### 20. DELETE /api/v1/customer/profile/avatar

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: Empty body.
- Optional query: none.
- Response shape: data.image_url.
- Expected statuses: 200,401,403,422,429; infrastructure exceptions return sanitized 500.
- Rules: Default avatar.jpg; physical files retained.

### 21. POST /api/v1/customer/change-password

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: JSON: `current_password`, `password`, `password_confirmation`.
- Optional query: none.
- Response shape: data.reauthentication_required:true.
- Expected statuses: 200,401,403,422,429; infrastructure exceptions return sanitized 500.
- Rules: Verify current password; revoke ALL personal tokens and outstanding reset proof; web sessions preserved.

### 22. GET /api/v1/customer/listings

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: `search`, `status`, `ad_type`, `sort`, `page`, `per_page`.
- Response shape: data.items + data.pagination; owner fields.
- Expected statuses: 200,401,403,422; infrastructure exceptions return sanitized 500.
- Rules: All own stored statuses;20/50; no public joins.

### 23. POST /api/v1/customer/listings

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: JSON: `ad_type`, `title`, `description`, `category_id`, `from_country_id`, `from_city_id`, `to_country_id`, `to_city_id`, `currency`, `departure_on`, `reached_on`, `weight`, `weight_price`.
- Optional query: none.
- Response shape: data: owner listing incl status,image_url.
- Expected statuses: 201,401,403,422,429; infrastructure exceptions return sanitized 500.
- Rules: Required type/title/category/route IDs/currency/dates; optional description,areas,weight,weight_price,show_contact_info(0 only). Carring/Send. Pending=0; owner derived.

### 24. GET /api/v1/customer/listings/{id}

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: none.
- Response shape: data: owner editable listing.
- Expected statuses: 200,401,403,404,422; infrastructure exceptions return sanitized 500.
- Rules: Pending/expired visible to owner; no GET writes.

### 25. PUT /api/v1/customer/listings/{id}

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: JSON: `title`.
- Optional query: none.
- Response shape: data: updated owner listing.
- Expected statuses: 200,401,403,404,409,422,429; infrastructure exceptions return sanitized 500.
- Rules: Editable partial fields; preserve status/owner/featured; no reapproval/republish.

### 26. DELETE /api/v1/customer/listings/{id}

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: Empty body.
- Optional query: none.
- Response shape: success:false; deletion unavailable.
- Expected statuses: 409,401,403,404,422,429; infrastructure exceptions return sanitized 500.
- Rules: Intentionally BLOCKED: owned listing always409. No deletion/archive/schema approval.

### 27. POST /api/v1/customer/listings/{id}/image

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: multipart/form-data File field **image**; do not set JSON Content-Type.
- Optional query: none.
- Response shape: data.image_url.
- Expected statuses: 200,401,403,404,409,422,429,500; infrastructure exceptions return sanitized 500.
- Rules: multipart image, safe JPEG/PNG/WebP <=2048KiB <=2048x2048; single image; old files retained.

### 28. DELETE /api/v1/customer/listings/{id}/image

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: Empty body.
- Optional query: none.
- Response shape: data.image_url.
- Expected statuses: 200,401,403,404,409,422,429; infrastructure exceptions return sanitized 500.
- Rules: Reset listing.png reference; preserve status/files.

### 29. GET /api/v1/customer/conversations

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: `page`, `per_page`.
- Response shape: data.items + data.pagination; metadata,last_message,last_message_at,unread_count.
- Expected statuses: 200,401,403,422; infrastructure exceptions return sanitized 500.
- Rules: 20/50; latest valid message ID then room ID descending; batched aggregates.

### 30. GET /api/v1/customer/conversations/{id}

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: none.
- Response shape: data.id,listing{id,title},other_participant{id,display_name},created_at.
- Expected statuses: 200,401,403,404,422; infrastructure exceptions return sanitized 500.
- Rules: Room membership + owner relation verified; no acceptance/contact access.

### 31. GET /api/v1/customer/conversations/{id}/messages

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: `page`, `per_page`.
- Response shape: data.items + data.pagination; id,conversation_id,sender{id,display_name},message,created_at,message_on,is_read.
- Expected statuses: 200,401,403,404,422; infrastructure exceptions return sanitized 500.
- Rules: Oldest persisted ID first;20/50; no GET read mutation.

### 32. POST /api/v1/customer/conversations

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: JSON: `post_ads_id`, `message`.
- Optional query: none.
- Response shape: data: conversation metadata.
- Expected statuses: 201,200,401,403,404,422,429; infrastructure exceptions return sanitized 500.
- Rules: New: public future listing, eligible owner, not self; existing pair earliest reuse with NO duplicate first message. No email. Global web uniqueness not guaranteed.

### 33. POST /api/v1/customer/conversations/{id}/messages

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: JSON: `message`.
- Optional query: none.
- Response shape: data: message object.
- Expected statuses: 201,401,403,404,422,429; infrastructure exceptions return sanitized 500.
- Rules: Only message accepted; max10000 chars/65535bytes; no NUL/empty; IDs derived; no attachments/mail.

### 34. POST /api/v1/customer/conversations/{id}/read

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: Empty body.
- Optional query: none.
- Response shape: data.conversation_id,marked_read,unread_count.
- Expected statuses: 200,401,403,404,422,429; infrastructure exceptions return sanitized 500.
- Rules: Only incoming NULL read_by -> caller ID string. Existing values preserved; idempotent.

### 35. GET /api/v1/customer/reviews/received

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: `page`, `per_page`.
- Response shape: data.items + data.pagination; id,listing,reviewer,reviewed_customer,rating,review,date_time,created_at,updated_at.
- Expected statuses: 200,401,403,422; infrastructure exceptions return sanitized 500.
- Rules: to_user_id=current; newest ID first;20/50.

### 36. GET /api/v1/customer/reviews/given

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: No body.
- Optional query: `page`, `per_page`.
- Response shape: data.items + data.pagination; same review object.
- Expected statuses: 200,401,403,422; infrastructure exceptions return sanitized 500.
- Rules: from_user_id=current; newest ID first;20/50.

### 37. POST /api/v1/customer/reviews

- Authentication: Bearer customer_token; active, verified, exclusively customer, mobile:account.
- Input: JSON: `post_ads_id`, `rating`, `review`.
- Optional query: none.
- Response shape: data: created review object.
- Expected statuses: 201,401,403,404,409,422,429; infrastructure exceptions return sanitized 500.
- Rules: rating0..5, required text; target listing owner; no self; duplicate reviewer/listing409. No public eligibility check: see high finding R03.

## Exact request examples and related contracts

The accompanying Postman collection contains all 37 method/path pairs, synthetic JSON bodies, file keys and disabled optional query parameters. Review JSON examples before sending: every mutation is an isolated-only operation. No auto token capture scripts exist.

Required registration inputs are name/email/phone/active country/password/confirmation/fresh CAPTCHA. Password defaults currently minimum8, bcrypt max72bytes. Listing creates require the complete route/category/type/title/currency/dates fields; Send and Carring use the same verified field set. Partial PUT profile/listing fields are permitted; privileged/unknown mutation fields rejected. Messages max10000 characters and65535bytes, no attachments/NUL. Reviews require text and integer0..5; no new text business cap. Reference search max100 and literal wildcard escaping; listings/owner search1..100. See existing detailed AUTH_V1.md, PUBLIC_V1.md, CUSTOMER_PROFILE_V1.md, CUSTOMER_LISTINGS_V1.md, CUSTOMER_CHAT_V1.md and CUSTOMER_REVIEWS_V1.md for individual field lengths and response examples; these older docs were not modified.

**Correction for integration:** current avatar controller accepts `avatar`, not `image`. Some prior overview examples incorrectly said image. Listing image upload still accepts `image`. Profile PUT response is nested in `data.profile` with verification/reauthentication flags, not a raw profile object.

Known availability gate: original database lacks personal_access_tokens; protected token operations are implemented/tested only against the existing isolated table. Listing DELETE remains409; no archive/delete functionality added. Review POST currently allows pending/expired listing IDs without public visibility checks; see R03 in final audit before release.


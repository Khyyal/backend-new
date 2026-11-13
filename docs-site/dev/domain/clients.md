# Clients

The **Clients** module owns client accounts and their relationship to centers.

## Sign-in and registration

Clients are created **by OTP verify** or **by a center**. In the app, `POST clients/auth/login` sends a code and `POST clients/auth/verify` checks it; the account is created through `ClientAuthService::findOrCreateByPhone` after a successful verify. A center creates a client without an account through `CenterClientService::create` (see below). Both fire `ClientRegistered`. See [Support](./support) for OTP rules.

A client **needs onboarding** while `first_name` or `last_name` is null. `PUT clients/profile` completes the profile; `GET clients/me` returns the current client.

A client changes their phone in two steps, both through `OtpVerificationService` with the login limits: `POST clients/phone` sends a code to the **new** phone and `POST clients/phone/verify` checks it, then `ClientAuthService::changePhone` saves it. The new phone must differ from the current one and must not belong to another non-deleted client; this is validated on both steps and re-checked under lock on save, because `clients.phone_number` has no unique index. The current token stays valid and the next login uses the new phone.

`POST clients/launch` must be called on every app start (30 per minute). It registers or refreshes the device with its FCM token, platform and locale. Without a token the device is stored unlinked and linked on verify when the same `device_identifier` is sent.

`GET clients/bookings` is one feed of reservations, packages and memberships, newest first.

## Relationship to centers

- A center sees only clients in `center_client`, or with a Confirmed or Completed purchase where it is the merchant. Never list clients across centers.
- A center can **block** a client (`POST …/blocked-clients`) and unblock them again (`DELETE …/blocked-clients/{client}`, 404 when the center has not blocked that client). Blocked clients cannot book that center.
- Clients are **soft-deleted**; raw queries must filter `deleted_at`.
- **Desk lookup and creation** (`centers/{center}/clients`, also under `admin/centers/{center}/clients`; `manageReservations`):
  - `GET …/lookup?phone_number=` is an exact match on `phone_number` across **all** clients, so the desk can book a client new to the center. Phones are stored as the client typed them at login (for example `+966500000003`), so the input must match that form. It returns `{id, first_name, last_name, phone_number}`, or 404 when no client has that phone (deleted clients are ignored). It does not add the client to the center.
  - `POST …` with `phone_number`, `first_name` and `last_name` creates a client under lock (the phone has no unique index; a taken phone is a 422 `validation.phone_taken`), adds them to `center_client` and fires `ClientRegistered` after commit. The client can later sign in by OTP with the same phone and keeps the same account, with onboarding already complete.

## Favorites and ratings

| Endpoint | Notes |
|---|---|
| `GET/POST clients/favorites`, `DELETE clients/favorites/{center}` | Favorite centers |
| `POST clients/ratings` | Rate a center. Aggregates feed center lists and `top_centers`. |
| `GET clients/ratings` | The client's own center ratings, 15 per page. `sort` (`RatingController::SORTS`): `newest` (default) or `highest` (most stars first, then newest). |
| `PATCH clients/ratings/{rating}` | Edit the `stars` and/or `comment` of one of the client's own ratings (404 otherwise); omitted fields keep their value. Goes through `RatingService::update` and flushes the center search cache. |

## Admin

`admin/clients` (list, show, search, export) with `clients.view_any`, `clients.view` and `clients.export`.

The list and export take `sort` (`AdminClientQuery::SORTS`): `name` (first, then last name), `created_at`, `centers_count` or `reservations_count`, `-` for descending; newest first without it. See [List sorting](../conventions/coding#list-sorting).

`POST admin/clients/{client}/otp` with `otps.create` issues a login code for the client and returns it once, without an SMS. See [Support: OTP](./support#admin-issued-codes).

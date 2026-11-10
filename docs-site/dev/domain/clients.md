# Clients

The **Clients** module owns client accounts and their relationship to centers.

## Sign-in and registration

Clients are created **only by OTP verify**: `POST clients/auth/login` sends a code, `POST clients/auth/verify` checks it. Create the account through `ClientAuthService::findOrCreateByPhone` after a successful verify, which fires `ClientRegistered`. See [Support](./support) for OTP rules.

A client **needs onboarding** while `first_name` or `last_name` is null. `PUT clients/profile` completes the profile; `GET clients/me` returns the current client.

`POST clients/launch` must be called on every app start (30 per minute). It registers or refreshes the device with its FCM token, platform and locale. Without a token the device is stored unlinked and linked on verify when the same `device_identifier` is sent.

`GET clients/bookings` is one feed of reservations, packages and memberships, newest first.

## Relationship to centers

- A center sees only clients in `center_client`, or with a Confirmed or Completed purchase where it is the merchant. Never list clients across centers.
- A center can **block** a client (`blocked-clients`). Blocked clients cannot book that center.
- Clients are **soft-deleted**; raw queries must filter `deleted_at`.

## Favorites and ratings

| Endpoint | Notes |
|---|---|
| `GET/POST clients/favorites`, `DELETE clients/favorites/{center}` | Favorite centers |
| `POST clients/ratings` | Rate a center. Aggregates feed center lists and `top_centers`. |

## Admin

`admin/clients` (list, show, search, export) with `clients.view_any`, `clients.view` and `clients.export`.

The list and export take `sort` (`AdminClientQuery::SORTS`): `name` (first, then last name), `created_at`, `centers_count` or `reservations_count`, `-` for descending; newest first without it. See [List sorting](../conventions/coding#list-sorting).

`POST admin/clients/{client}/otp` with `otps.create` issues a login code for the client and returns it once, without an SMS. See [Support: OTP](./support#admin-issued-codes).

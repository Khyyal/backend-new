# Centers

The **Centers** module owns centers, the users who run them, and the center-facing API surface.

## State and status

| Field | Values | Controlled by | Meaning |
|---|---|---|---|
| `state` | `pending`, `active`, `blocked` | Admin only | Gates every center-scoped endpoint. Only `centers.show` works unless the state is `active`. |
| `status` | `visible`, `invisible` | The center | Whether the center is listed to clients. |

Changing `state`, `status`, custom site or domain is admin-only. Clients only see **visible, active** centers.

## Center users

- The **primary user** (`is_primary`, role `owner`) implicitly holds every center permission and can never be modified.
- Other users get a list of `CenterUserPermission` values. A manager can only grant permissions they hold and cannot modify themselves.
- Removing a user revokes their center tokens. A user with no centers left is soft-deleted.
- The primary user updates their own `name` and access password with `PUT|PATCH centers/{center}/profile` (`UpdateProfileRequest`, gated by `CenterPolicy::update`). Changing an already-set password needs `current_password`; the phone cannot be changed there.
- The primary user deletes the account with `DELETE centers/{center}` (`CenterPolicy::deleteOwn`, `CenterAccountService::delete()`). It is refused with a 422 and a stable `reason` (`CenterDeletionNotAllowedException`, messages in `centers::deletion`) when the center has upcoming `pending`/`confirmed` reservations (`has_open_reservations`) or a pending payout or a non-zero balance of ledger entries not yet in a payout (`has_unsettled_balance`). Otherwise, in one transaction, every user is detached through `CenterUserManagementService::detachAll()` (tokens revoked, accounts without another center soft-deleted) and the center is soft-deleted. The admin delete (`CenterAdminService::delete()`) detaches users the same way, without the guards.
- Center login uses a phone OTP (`centers/auth/phone`, `centers/auth/phone/verify`); `POST centers/{center}/access` issues the center-scoped token and asks the primary user for their password again.
- Admins list a center's users with `GET admin/centers/{center}/users` (`centers.view`), primary user first. With `otps.create`, `POST admin/centers/{center}/users/{user}/otp` issues a login code for one of them. See [Support: OTP](./support#admin-issued-codes).
- Admins manage a center's users under `admin/centers/{center}/users` through `CenterUserManagementService::createByAdmin()`, `updateByAdmin()` and `removeByAdmin()`. Listing, showing and `GET …/users/permissions` need `centers.view`; adding, updating and removing need `centers.update` (`CenterPolicy::manageUsersAsAdmin`). Admins may grant any permission and may change a user's phone (unique across center users). A changed phone clears `phone_verified_at` and revokes every token of the account, which may serve other centers too. For the primary user admins can change only `name` and `phone`; it still cannot be removed.

See [Roles and permissions](../conventions/permissions).

## Admin center list

`GET admin/centers` (`centers.view_any`) is built by `CenterAdminService::query()`, which `POST admin/centers/export` reuses, so both take the same filters (`search` on name, contact phone and email; `status`, `state`, `city_id`, `from`/`to` on the creation date) and the same `sort`.

- `sort` is one of `CenterAdminService::SORTS`: `name`, `created_at` or `points`, with a `-` prefix for descending (`-points`). Ties are broken by `id` in the same direction. See [List sorting](../conventions/coding#list-sorting).
- Without `sort` the list is newest first (`id` descending). Any other value is a 422.
- `CenterResource` includes `created_at` (ISO-8601) so the panel can show and sort by the creation date.

## Client-facing endpoints

| Endpoint | Notes |
|---|---|
| `GET clients/centers` | Paginated list of visible centers, optional `lat` and `lng` for distance |
| `GET clients/centers/{slug}` | Center details with an overview of its **active** services |
| `GET/POST/DELETE clients/favorites` | Favorites |
| `POST clients/ratings` | Rate a center |

The center details response lists each active service with `start_price` and `display_price`:

- `start_price` is the lowest price option in halalas. For a **Resort**, add-ons (price options without a quantity) are not counted.
- `display_price` is `null`, or `{original, discounted, discount_amount, badge}` while an automatic offer lowers the start price right now. It comes from `ServicePriceDisplay` (see [Promotions](./promotions)).

## Profile completion and launch

`GET centers/{center}/profile-completion` reports what is missing before a center can launch. `POST centers/launch` and `POST centers/register` create centers; admins can also create them.

## Other center settings

Site theme (`site-theme`), social links (`social-links`) and the payout bank account (`payout-account`) are updated through their own endpoints. The center sees **analytics** at `centers/{center}/analytics`, and its purchases, clients, ledger and exports under the same prefix.

## Business rules

- A center sees only its **own clients**: clients in `center_client`, or with a Confirmed or Completed purchase where the center is the merchant. Never list clients across centers.
- Revenue and analytics count only **Confirmed** and **Completed** purchases where the center is the merchant, compared with the previous period of the same length. Analytics are center-wide and not narrowed by purchase visibility.
- Center promotions apply only to that center's services. See [Promotions](./promotions).

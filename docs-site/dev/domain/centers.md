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
- Center login uses a phone OTP (`centers/auth/phone`, `centers/auth/phone/verify`); `POST centers/{center}/access` issues the center-scoped token and asks the primary user for their password again.

See [Roles and permissions](../conventions/permissions).

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

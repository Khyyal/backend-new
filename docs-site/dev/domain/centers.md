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
- The primary user deletes the account with `DELETE centers/{center}` (`CenterPolicy::deleteOwn`, `CenterAccountService::delete()`). It is refused with a 422 and a stable `reason` (`CenterDeletionNotAllowedException`, messages in `centers::deletion`) when the center has upcoming `pending`/`confirmed` reservations (`has_open_reservations`) or a pending payout or a non-zero balance of ledger entries not yet in a payout (`has_unsettled_balance`). Otherwise, in one transaction, the center is soft-deleted and its users are cut off through `CenterUserManagementService::suspendAll()`: their center tokens are revoked and accounts without another center are signed out and soft-deleted, but assignments and center permissions are kept. The admin delete (`CenterAdminService::delete()`) does the same, without the guards.
- `POST admin/centers/{center}/restore` (`centers.restore`, `CenterPolicy::restore`, `CenterAdminService::restore()`) restores a soft-deleted center with the `state` and `status` it had, and `CenterUserManagementService::reinstateAll()` restores the accounts that were soft-deleted with it, so the owner and team return with their permissions. A center that is not deleted is a 404. Centers deleted before assignments were kept come back without users.
- Center login uses a phone OTP (`centers/auth/phone`, `centers/auth/phone/verify`); `POST centers/{center}/access` issues the center-scoped token and asks the primary user for their password again.
- Admins list a center's users with `GET admin/centers/{center}/users` (`centers.view`), primary user first. With `otps.create`, `POST admin/centers/{center}/users/{user}/otp` issues a login code for one of them. See [Support: OTP](./support#admin-issued-codes).
- Admins manage a center's users under `admin/centers/{center}/users` through `CenterUserManagementService::createByAdmin()`, `updateByAdmin()` and `removeByAdmin()`. Listing, showing and `GET …/users/permissions` need `centers.view`; adding, updating and removing need `centers.update` (`CenterPolicy::manageUsersAsAdmin`). Admins may grant any permission and may change a user's phone (unique across center users). A changed phone clears `phone_verified_at` and revokes every token of the account, which may serve other centers too. For the primary user admins can change only `name` and `phone`; it still cannot be removed.

See [Roles and permissions](../conventions/permissions).

## Admin center list

`GET admin/centers` (`centers.view_any`) is built by `CenterAdminService::query()`, which `POST admin/centers/export` reuses, so both take the same filters (`search` on name, contact phone and email; `status`, `state`, `city_id`, `from`/`to` on the creation date) and the same `sort`.

- `sort` is one of `CenterAdminService::SORTS`: `name`, `created_at` or `points`, with a `-` prefix for descending (`-points`). Ties are broken by `id` in the same direction. See [List sorting](../conventions/coding#list-sorting).
- Without `sort` the list is newest first (`id` descending). Any other value is a 422.
- `trashed` adds soft-deleted centers to the list and the export: `with` returns deleted and live centers, `only` returns deleted ones. Without it deleted centers are left out. Any other value is a 422.
- `GET admin/centers/{center}` (`centers.view`) also returns a soft-deleted center (the route is bound `withTrashed`). `POST admin/centers/{center}/restore` is the only other route that accepts a deleted center; the rest stay a 404.
- `CenterResource` includes `created_at` (ISO-8601) so the panel can show and sort by the creation date, and `deleted_at` (ISO-8601, `null` unless the center is deleted).
- `points` (the popularity score behind the client `most_popular` order) is set with `PATCH admin/centers/{center}` (`centers.update`): an integer of 0 or more. Only admins can change it; the center's own update ignores it.
- `GET centers/{center}`, the `center` of `POST centers/{center}/access` and every center update return the center with `city`, `tags`, `logo`, `cover` and `images`. `CenterUpdateService` loads them after its transaction commits, because replaced and removed media are deleted after commit; the response therefore lists only the media the center still has.

## Client-facing endpoints

| Endpoint | Notes |
|---|---|
| `GET clients/centers` | Paginated list of visible centers, optional `lat` and `lng` for distance |
| `GET clients/centers/{slug}` | Center details with an overview of its **active** services; `include=ratings` adds ratings |
| `GET clients/centers/by-domain/{domain}` | `{id, slug, site_theme}` of the center behind a custom site. Matched case-insensitively; only **visible, active** centers with `custom_site_enabled`, otherwise 404. `include=ratings` adds ratings |
| `GET/POST/DELETE clients/favorites` | Favorites |
| `POST clients/ratings` | Rate a center |
| `GET clients/ratings`, `PATCH clients/ratings/{rating}` | List (`sort=newest\|highest`) and edit the client's own ratings, see [Clients](./clients#favorites-and-ratings) |

The center details response lists each active service with `start_price` and `display_price`:

- `start_price` is the lowest price option in halalas. For a **Resort**, add-ons (price options without a quantity) are not counted.
- `display_price` is `null`, or `{original, discounted, discount_amount, badge}` while an automatic offer lowers the start price right now. It comes from `ServicePriceDisplay` (see [Promotions](./promotions)).

Both client center reads (`{slug}` and `by-domain/{domain}`) take `include`, a comma-separated list of extras (`ParsesCenterIncludes::INCLUDES`); any other value is a 422. Today it accepts only `ratings`, which adds `ratings: {summary: {average, count}, data}`. `summary` covers every rating of the center (`average` rounded to 2 decimals, `null` without ratings). `data` holds the `CenterDetailsService::RATINGS_LIMIT` (10) highest ratings, most stars first, then newest, each with `id`, `client` (rater full name), `stars`, `comment` and `rated_at`.

## Profile completion and launch

`GET centers/{center}/profile-completion` reports what is missing before a center can launch. It has 10 steps; the last one, `email`, is done only when the center's contact email is set **and verified**. `POST centers/launch` and `POST centers/register` create centers; admins can also create them.

## Email verification

The center's contact email is verified with a 6-digit code (`CenterEmailVerification`). The primary user calls `POST centers/{center}/email/send` (mails the code in the request locale through the queued `CenterEmailVerificationMail`; valid 10 minutes, stored hashed in `email_verification_code`, never serialized; one send per 60 seconds and 5 per 15 minutes per center) and then `POST centers/{center}/email/verify` with `code` (5 wrong attempts per 5 minutes). Success sets `email_verified_at` and clears the code. Without an email, or when it is already verified, both fail with a 422 on `email`. Any change of `email` (center update or admin edit, through `CenterUpdateService`) clears `email_verified_at` and the pending code; saving the same email keeps it. Existing centers start unverified. Sending real mail needs a configured `MAIL_*` mailer (the default is `log`).

## Other center settings

Site theme (`site-theme`), social links (`social-links`) and the payout bank account (`payout-account`) are updated through their own endpoints. The center manages its blocked clients under `centers/{center}/blocked-clients` (list, block, and `DELETE …/{client}` to unblock; also under `admin/centers/{center}`), see [Clients](./clients#relationship-to-centers). The center sees **analytics** at `centers/{center}/analytics`, and its purchases (list, show, export and `purchases/{purchase}/refund`, see [Purchase](./purchase#refunding-a-purchase-from-the-center)), clients, ledger and exports under the same prefix.

`GET centers/{center}/ratings` (also under `admin/centers/{center}`; `ratings.view` or `centers.view_ratings`) returns a `summary` (`average`, `count`) and every rating, not paginated. `sort` (`Center\RatingController::SORTS`) is `newest` (default) or `highest` (most stars first, then newest); any other value is a 422.

## Horses

A center keeps a list of its horses (`Horse`, table `horses`): `name` (plain string), `room` (positive integer, rooms may be shared) and an `image` (single-file media collection, attached only through temporary uploads). A horse is **center-owned** when `client_id` is null, otherwise **client-owned**. Naming any existing, non-deleted client makes them the owner, and `CenterHorseService` adds them to `center_client` (`syncWithoutDetaching`) so the center can see them. Update replaces `name`, `room` and `client_id` (null makes the center the owner); an omitted `image` is kept, null removes it after commit. Deleting soft-deletes the horse.

CRUD lives under `centers/{center}/horses` (`horses.manage`) and `admin/centers/{center}/horses` (`centers.manage_horses`), authorized by `CenterPolicy::manageHorses`. The list is paginated (15, newest first) and filters by `owner` (`center`/`client`), `client_id`, `trainer_id` and `q` (name). Clients do not see horses yet.

Horses and trainers are many-to-many (`horse_trainer` pivot with timestamps; `Horse::trainers()`, `Trainer::horses()`). `PUT centers/{center}/horses/{horse}/trainers` (and under `admin/centers/{center}`; `manageHorses`) takes the full `trainer_ids` list (max 50, live trainers of the same center, distinct) and syncs it under a lock on the horse; `[]` unlinks all. `HorseResource` returns `trainers` (`id`, `name`); soft-deleted trainers keep their pivot rows but are not shown.

## Business rules

- A center sees only its **own clients**: clients in `center_client`, or with a Confirmed or Completed purchase where the center is the merchant. Never list clients across centers.
- Revenue and analytics count only **Confirmed** and **Completed** purchases where the center is the merchant, compared with the previous period of the same length. Analytics are center-wide and not narrowed by purchase visibility.
- Center promotions apply only to that center's services. See [Promotions](./promotions).

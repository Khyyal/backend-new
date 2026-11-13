# Services

A **Service** is something a center sells. Every service has a `type`, belongs to one center, and points to a type-specific record (its *serviceable*).

## Service types

| Type | Serviceable | Booked by |
|---|---|---|
| `recreation_riding` | `RecreationalRiding` | Hourly slot with a schedule |
| `visit` | `Visit` | Day or specific time, shared daily capacity |
| `event` | `Event` | Ticket for a dated event |
| `resort` | `Resort` | A date range, priced per day, with add-ons |
| `horse_care` | `HorseCare` | Price options only |
| `horse_service` | `HorseService` | Price options only |
| `training` | `TrainingService` | Hourly slot with a trainer |
| `membership` | `Membership` | A paid membership with packages and visits |
| `stabling` | `Stabling` | Not bookable: monthly prices, optional daily price and extras |

Only riding, visit, event, resort and training are bookable through reservations. See [Reservations](./reservations).

## Rules

- **Scoped to the route center.** Filter every center endpoint by `center_id` and validate every cross-reference (block `service_id`, membership `visit_service_id` and packages, training trainers) against the route center.
- **Authorization by type.** `Gate::authorize('manageServices', [$center, ServiceType::X])`. Trainers fall under Training. Center-wide blocks and gender periods need `manageAllServices`.
- **Writes lock and keep identity.** Create and update inside `DB::transaction` with `lockForUpdate` on the Service. New services start `ACTIVE`. `type`, `center_id` and `slug` never change on update.
- **Price options update in place.** Update duration price options by id so sold packages survive. Options missing from the payload are soft-deleted together with their package. There is one `ServicePackage` per price option; revive a trashed package instead of duplicating.
- **Package status.** A `ServicePackage` has a `status` (`ActivationStatus`, default `active`). `packages.*.status` is optional: when omitted, a live package keeps its status and a new or revived one is active. Inactive packages stay in the center resources but clients get `package: null` for them, and `ServicePackage::isSellable()` (active and not trashed) gates package purchases and membership availability.
- **Settings** are boolean rows unique per `(service_id, key)`, limited to the model's `SETTINGS_KEYS` (for example `enable_cash_on_arrival`, `enable_single_session_booking`, `enable_package_booking`).
- **Stabling prices** live in `price_options`, told apart by `unit`: monthly prices are `month` with `quantity` = months (1-24, at least one, distinct), the optional daily price is `day` with `quantity` 1, extras are `option` with a `name` and no quantity. The API exposes them as `monthly_prices`, `daily_price` and `extra_options`. `StablingService` updates them in place by `id` (an id of another service is a 422) and soft-deletes options missing from the payload.
- **Deletion** soft-deletes the service, its serviceable and its price options. History and analytics read `withTrashed()`.
- **Visibility.** Clients only see `ACTIVE` services of visible, active centers.

## Prices on the client API

| Field | Meaning |
|---|---|
| `start_price` | Lowest price option in halalas. Built by `Service::withStartPrice()`: a Resort ignores add-ons and a Stabling ignores extras (price options with no quantity). `null` when there is none. |
| `display_price` | `null`, or `{original, discounted, discount_amount, badge}` while an automatic offer lowers the start price right now. |

`display_price` is returned by the homepage curated services, the offers list, the center details services and the service details endpoint. Prices inside `details.price_options` are not discounted.

## Client endpoints

| Endpoint | Notes |
|---|---|
| `GET clients/services` | Search across visible services |
| `GET clients/centers/{center}/services/{slug}` | Details with the type-specific `details`; riding, event and training include upcoming availability |
| `GET clients/services/{service}/availability` | Availability for booking |

## Center and admin endpoints

Each type has CRUD under `centers/{center}/<type>` (`recreation-ridings`, `visits`, `events`, `resorts`, `horse-cares`, `horse-services`, `training-services`, `memberships`, `stablings`), mirrored under `admin/centers/{center}/...`. Also `trainers`, `service-blocks`, `periods`, `service-types` and `service-types/{type}/terms`.

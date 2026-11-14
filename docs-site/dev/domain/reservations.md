# Reservations

The **Reservations** module books slots, enforces capacity and runs the reservation lifecycle, including packages and memberships.

## Reservation code

Every reservation gets a unique public `code` on insert (`HasUniqueCode` in `modules/Support/src/Concerns`): 6 characters from `A-Z` and `2-9` without `I`, `L`, `O`, `0` and `1`, e.g. `K7QX2M`. It is never fillable; a unique index on `reservations.code` is the final guard. Lookups by code ignore case and surrounding spaces (`whereCode()`). The code is in every reservation resource.

## Status lifecycle

```
pending ─► confirmed ─► completed
   │           ├────────► no_show
   └───────────┴────────► cancelled
```

| Rule | Detail |
|---|---|
| Single entry point | Change status only through `ReservationStateService`. It locks the row, re-checks the status and fires its event after commit. |
| `completed` / `no_show` | Only from `confirmed`, and only after the slot started. |
| Reschedule | Only while `pending` or `confirmed`. |
| Capacity | `pending`, `confirmed`, `completed` and `no_show` occupy capacity; `cancelled` does not. Use `Reservation::occupyingCapacity()`. |

## Quoting and inserting

Call `ReservationRules::quote()` inside a transaction that already holds `CapacityLock` (the service row, plus the trainer row when a trainer is set), and insert the reservation before commit. Overlap is half-open `[start, end)`. Capacity sums `quantity`; `null` capacity means unlimited.

## Booking violations

Throw `ReservationNotAllowedException::because('<key>')`. It answers 422 with a `reason`, and the message comes from `reservations::booking.<key>`. **Treat the keys as public API.** Form Requests validate shape only; bookability is decided by the Booking rules classes.

## Guards for every service type

- `quantity >= 1`
- The service is `ACTIVE`
- The client is not blocked by the center
- No center-wide Resort `close_all_*` closure for the type
- The single-session or package setting is on (only when the type declares it)
- The slot is not in the past
- No overlapping `ServiceBlock` (`service_id = null` means center-wide)

## Rules per type

| Type | Rules |
|---|---|
| Riding, Training | Needs a minute price option (a package option wins) and `start_time`. Slot = start + option minutes; it may end at 24:00 but not cross midnight and must fit one schedule window. Capacity is `max_tickets_per_hour`. Training uses the trainer's schedule and `maxTicketsPerHourOn(weekday)`, counted by `trainer_id` across all services. Package bookings are quantity 1 and price 0 for the same client and service. |
| Visit | No packages. The day must have a schedule; `specific_time` must equal a window start; `max_tickets_per_day` is shared across enter types. |
| Event | No packages. One ticket line. The date is within `start_date`–`end_date` and today is within `open_date`–`close_date`. |
| Resort | No packages. Quantity 1, inclusive date range, every day priced and unblocked. Any occupying overlap gives `resort_unavailable`. Add-ons once per stay. |
| Stabling | Center-only, through `POST centers/{center}/reservations/stabling` (also under `admin/centers/{center}`; `manageReservations`, idempotent). `StablingRules` needs a `horse_id` (`horse_required` otherwise, so client and generic bookings cannot book it). The horse must be client-owned (`horse_not_client_owned`) and its owner is the client. Quantity 1, no packages, no reschedule (`stabling_reschedule_not_supported`). A monthly option ends on `start + N months − 1 day` (`addMonthsNoOverflow`); the daily option opens a stay with `end_date` null, charged one day, that lasts until completed or cancelled. The horse must have no other pending or confirmed stabling stay overlapping (null end date = unbounded), else `horse_unavailable`; `CapacityLock` locks the horse row. An all-day block over the stay (the first day only for open-ended stays) gives `slot_blocked`. Extras (`addon_ids`) are added once per stay. `is_paid` true pays with `manual` (everything confirmed); false uses `cash_on_arrival` (confirmed, payment pending; stabling has no settings, so `enable_cash_on_arrival` does not apply). `total_amount` sets `BookingRequest::totalOverride`: the reservation's `total_amount` becomes it, `subtotal` keeps the computed price, `metadata.price_override.calculated_total` records it, and `DiscountInput::applyPromotions` false skips offers, coupons and the expected-total check. `POST …/reservations/{reservation}/end-stay` (`editReservations`, idempotent) runs `ReservationStateService::endStay`: only a confirmed stabling stay that has started (`not_a_stabling_stay`, `invalid_transition`, `not_started_yet`); `end_date` (default today) must be from the start date to today and not after a planned end (`invalid_stay_end`). It sets `end_date`, keeps the previous one in `metadata.planned_end_date`, completes the reservation (`ReservationCompleted`) and frees the horse. The price and payment do not change. **Stay invoices**: `POST …/reservations/{reservation}/invoices` (`editReservations`, idempotent) runs `CreateStayInvoice` and `GET` lists them (`manageReservations`, newest first). A `StayInvoice` (morph `stay_invoice`, a purchasable) bills the stay's client for a `horse_service` or `horse_care` (an active service of that type in the center and one of its price options, else `invalid_invoice_service` / `invalid_price_option`; name snapshot = service and option name) or a `standalone` line (`name`, optional `description`, required `total_amount`). Only a confirmed stabling stay (`not_a_stabling_stay`, `invoice_stay_not_active`), on a date within it (`invoice_date_outside_stay`; open-ended stays have no upper bound). `total_amount` replaces the option price (`metadata.price_override.calculated_total`); offers and coupons never apply (`DiscountInput::applyPromotions` false). `is_paid` true checks out with `manual` (confirmed), false with `cash_on_arrival` (payment pending); a 0 total creates no purchase. `service_id` is the billed service, or the stay's stabling service for standalone invoices, so `CenterPurchaseVisibility` treats it like packages (service type permission plus `purchases.view_unassigned`). `POST …/invoices/{invoice}/cancel` (`editReservations`, idempotent, optional `reason`) runs `CancelStayInvoice`: a paid invoice (confirmed purchase) is refunded in full through `RefundPurchase` (a manual payment is recorded as refunded; the fee is reversed), an unpaid one has its pending payments and its purchase cancelled, a free one is only marked. The invoice gets `cancelled_at`, `cancelled_by` and `cancellation_reason` (`invoice_already_cancelled` on a second try); the stay is not changed. `RefundPurchase` also marks a stay invoice cancelled, so refunding its purchase from the purchases screen gives the same result. `StayInvoiceResource` returns `refunded_amount`, `cancelled_at` and `cancellation_reason`. |
| Others | Not bookable. |

## Horses and stays board

`GET centers/{center}/horse-stays` (also under `admin/centers/{center}`) lists, ordered by horse name and 25 per page, the center's **active stays** and its **horses without an active stay**. It needs `horses.manage` or `reservations.manage` (`CenterPolicy::viewHorseStays`; system users `centers.manage_horses` or `centers.manage_reservations`). An active stay is a stabling reservation that is `confirmed` and has started (`date` ≤ today); an overdue stay stays active until it is ended, and a future stay leaves its horse among the others. `type` is `all` (default), `active` or `others`; `q` matches the horse name or the client's first name, last name or phone, every word must match. `HorseStayBoardQuery` paginates a union of both selects, then loads the page. Each item has a `type`: a `stay` has `status`, `end_date`, `horse` (`id`, `name`, `image`, `room`, `client`), `service` (`id`, `name`) and `unpaid_operations_count` (the stay and its invoices whose purchase is `pending`); a `horse` has `name`, `image`, `room`, `client` and `trainers`.

`GET centers/{center}/horse-stays/counts` (same access, also under `admin/centers/{center}`) returns `data.all`, `data.active` and `data.others`: how many items each `type` lists, with the same `q` search applied (`type` itself is ignored). `HorseStayBoardQuery::counts` counts the two selects of the list, so `all` = `active` + `others`.

`GET centers/{center}/horse-stays/{reservation}` (same access, also under `admin/centers/{center}`) shows one stabling stay of the center in any status (404 for another type or center) as a `StayDetailsResource`: every `CenterReservationResource` field (code, dates, price option, extra `lines`, prices, client, `purchase` summary), the `horse` with `image`, `room`, `client` and `trainers`, `planned_end_date` (from `metadata` after an early end, else null), `unpaid_operations_count`, and `invoices` (`StayInvoiceResource`, newest first). The unpaid counts come from `HorseStayBoardQuery::withUnpaidCounts`, shared with the list.

## Cancellation

Only **client** cancellers are bound by `reservations.client_cancellation_cutoff_hours`. Cancelling:

- refunds `min(item total, refundable)` through the gateway for online payments, and **aborts the cancel if the refund fails**
- flags manual payments `refund_manual_required`
- gives no refund for cash or pending payments
- restores one package credit

## Reschedule

Re-runs the full booking rules with `ignoreReservationId`, keeps the same service, and must not change the subtotal (`reschedule_price_changed`). A different price means cancel and rebook. The previous slot is appended to `metadata.reschedules`. When the new slot has too few places (`capacity_exceeded`, or `resort_unavailable` for resorts) the reschedule answers **409** with that `reason` and `available`, and moves nothing; every other rule failure stays a 422. `force=true` sets `BookingRequest::ignoreCapacity` and skips only the capacity checks (hourly, visit, event and resort overlap), so the booking can overbook the slot; schedule, blocks, past dates, state and the unchanged-price rule still apply. Create, quote and checkout keep answering 422 for capacity.

## Packages and memberships

- `BookFromPackage` locks the `ClientPackage`, enforces a trainer-bound package's trainer, creates the reservation **Confirmed at price 0**, and decrements `remaining_sessions` (0 means `exhausted`).
- Membership visits need a usable membership before `expires_at` and a daily count (under lock) within `max_visits_per_day`. They are free and Confirmed.
- Only **sellable** packages (active and not deleted) can be bought or quoted: `service_package_id` must exist with `status = active`. A membership whose included package is not sellable fails with `membership_package_unavailable`.
- Packages and memberships are **never sold as cash on arrival**. They are created `pending` (membership packages at price 0) and activate on `PurchaseConfirmed`, with `expires_at` counted from activation (`servicePackage.duration` or the membership duration in months). Listeners are idempotent and only touch records still pending.
- `ClientPackage.knight_name` (nullable string, max 255) is set only by the owning client (`PATCH clients/packages/{package}`); `controlled` (bool, default false) is set only by center staff or admins (`PATCH centers/{center}/packages/{package}`, `reservations.manage`, paid packages of that center only). Both appear in every package resource.
- Center staff manage a paid package of their center (same permission, also under `admin/centers/{center}/packages`): `POST packages/{package}/reservations` books a Confirmed price-0 session through `BookFromPackage` with source `center`; `POST packages/{package}/sessions` adds sessions (raises `total_sessions` and `remaining_sessions`, Exhausted goes back to Active, or stays Expired when the validity passed; cancelled packages are refused with `package_not_adjustable`); `PATCH packages/{package}` takes `controlled`, `trainer_id` and `expires_at` (at least one). The trainer must belong to the center and teach the package's training service (`invalid_trainer`; other service types `package_not_trainer_bound`; null frees it). A new expiry must be in the future and later than the current one (`invalid_expiry`); it resets `expiry_reminder_sent_at` and turns an Expired package with sessions left back to Active.
- Center staff cancel a paid package with `POST packages/{package}/cancel` (`reservations.manage`, idempotent; `CancelClientPackage::execute`). It is refused when the package is already cancelled (`package_not_cancellable`), came with a membership (`package_membership_bound`), or money is held for it: its purchase has a `processing` payment or a `succeeded` one not fully refunded (`package_has_payment`; refund the purchase instead). Otherwise the package's Pending/Confirmed reservations that have not started are cancelled through `ReservationStateService` (reason `package_cancelled`), the package becomes `cancelled`, `ClientPackageCancelled` fires after commit (client notification `client_package_cancelled`), and a Confirmed purchase whose only item is the package is cancelled with its pending payments.
- `RefundPurchase` (`POST centers/{center}/purchases/{purchase}/refund`, see [Purchase](./purchase#refunding-a-purchase-from-the-center)) cancels what a refunded purchase sold: Pending/Confirmed reservations (reason `purchase_refunded`), packages through `CancelClientPackage::void()` (no payment or membership guard), and memberships with their packages and upcoming visits.
- Center staff extend a paid, uncancelled client membership with `PATCH client-memberships/{membership}` (`expires_at`, same permission, also under `admin/centers/{center}/client-memberships`): the new expiry must be in the future and later than the current one (`invalid_expiry`), `expiry_reminder_sent_at` is reset and an Expired membership becomes Active. Pending and cancelled memberships fail with `membership_not_adjustable`. The packages that came with the membership keep their own validity.
- `client_membership_logs` is an append-only log per membership (`type` `invite` or `check_in`, optional `reservation_id` and `guest_client_id`, polymorphic `actor`, `notes`). A member invites another existing client with `POST clients/memberships/{membership}/invites` (`guest_client_id`, `date`, `start_time`): `BookVisitFromMembership` books one Confirmed free visit owned by the guest (so it counts against the member's `max_visits_per_day`, checked under the membership lock) and writes the `invite` entry in the same transaction; nothing is logged when the booking fails. Center staff add a `check_in` entry with `POST client-memberships/{membership}/check-ins` (`notes`; the membership must be usable; no reservation is touched; every call adds a row) and read the log with `GET client-memberships/{membership}/logs` (`type` filter, 25 per page, newest first).

## Checkout

Checkout accepts only `pending`, non-package reservations with total > 0, of **one client and one center**, without an existing purchase item.

- Cash on arrival needs `enable_cash_on_arrival` on every service and confirms immediately.
- Online payment leaves the reservations `pending`.
- A total ≤ 0 confirms with no purchase.
- Booking and checkout share one transaction.

## Unpaid holds

`reservations:release-unpaid` cancels pending online purchases older than `unpaid_hold_minutes`, but only if, re-checked under lock, they have no `processing`, `succeeded` or `refunded` payment. Pending payments stay open. A payment that succeeds on an already-cancelled purchase is **refunded automatically**.

## Purchase summary on resources

`ReservationResource`, `ClientPackageResource`, `ClientMembershipResource` and their center variants embed `purchase` (`PurchaseSummaryResource`): `id`, `code`, `status`, `channel`, `total_amount`, `discount_amount`, `coupon_code`, `payment_method`, `payment_status`, `created_at`, plus `client` (`id`, `full_name`, `phone_number`) and `center` (`id`, `name`, `city`, `address`, `lat`, `lng`, `contact_phone`). It comes from the record's latest `purchaseItem` and is `null` when the record was not sold through a purchase (a session drawn from a package or membership, packages included in a membership). It is only present when the controller loads `PurchaseSummaryResource::RELATIONS`, so nested resources (a membership's `packages`) omit it.

## Client endpoints

`reservations` (list, show, show by `code/{code}`, create, `quote`, `cancel`), `purchases` (the client's own purchases, newest first, 15 per page, optional `status`), `purchases/{purchase}` and `purchases/code/{code}` (one of the client's purchases, `ClientPurchaseResource`; 404 for anyone else's), `bookings`, `packages` and `memberships` (list, `quote`, buy), `PATCH packages/{package}` (set `knight_name`), `memberships/{membership}/invites`, `packages/{package}/reservations`, `memberships/{membership}/visits`.

## Center endpoints

`centers/{center}/reservations` (and the same under `admin/centers/{center}/reservations`): list, `calendar`, show, show by `code/{code}` (404 for another center's code), create, `quote`, `reschedule`, `cancel`, `complete`, `no-show`, `PUT …/{reservation}/horse`, `POST stabling` and `POST …/{reservation}/end-stay`.

**Horse.** A reservation has an optional `horse_id` (nullable FK to `horses`, null on delete). `PUT centers/{center}/reservations/{reservation}/horse` with `horse_id` (`editReservations`) sets it to any non-deleted horse of the same center, whoever owns it, or clears it with `null`. It works in any status and does not touch the slot, price or status. `CenterReservationResource` returns `horse_id` and `horse` (`id`, `name`, `room`).

---
paths:
  - 'modules/Reservations/**'
---

# Reservations

## Reservation status changes only via ReservationStateService
Allowed: pending->confirmed, pending|confirmed->cancelled, confirmed->completed|no_show (completed/no_show only after the slot started), reschedule only while pending|confirmed. Never write status directly; each transition locks the row, re-checks status, and fires its event after commit.

## Capacity statuses come from occupyingCapacity()
Pending, Confirmed, Completed and NoShow occupy capacity; Cancelled does not. Use Reservation::occupyingCapacity() / ReservationStatus instead of re-listing statuses.

## Quote and insert under CapacityLock
Call ReservationRules::quote() inside a transaction that already holds CapacityLock (service row, plus trainer row when a trainer is set) and insert the reservation before commit. Overlap is half-open [start, end); capacity sums quantity; null capacity means unlimited.

## Booking violations use stable reason keys
Throw ReservationNotAllowedException::because('<key>') (422 with reason, message from reservations::booking.<key>); treat keys as public API. FormRequests (HasBookingRules) validate shape only; bookability is decided by the Booking rules classes.

## Shared booking guards apply to every service type
Every booking needs quantity >= 1, an ACTIVE service, a client not blocked by the center, no center-wide Resort close_all_* closure for the type, the single/package enable setting on (only when the type declares it), a slot not in the past, and no overlapping ServiceBlock (service_id null = center-wide).

## Hourly booking rules (riding and training)
Require a minute price option (package option wins) and start_time; slot = start + option minutes, may end at 24:00 but not cross midnight, must fit one schedule window; capacity is max_tickets_per_hour. Training uses the trainer's schedule and maxTicketsPerHourOn(weekday), counted by trainer_id across all services. Package bookings are quantity 1, price 0, same client and service.

## Visit, Event and Resort booking rules
Visit: no packages, day must have a schedule, specific_time must equal a window start, max_tickets_per_day shared across enter types. Event: no packages, one ticket line, date within start_date-end_date and today within open_date-close_date. Resort: no packages, quantity 1, inclusive range, every day priced and unblocked, any occupying overlap = resort_unavailable, addons once per stay. Stabling: center-only via POST reservations/stabling (StablingRules needs horse_id), for a client-owned horse (owner = client), quantity 1, no packages or reschedule; a monthly option ends start + N months - 1 day, the daily option is open-ended (end_date null); the horse must have no pending/confirmed stabling stay overlapping (null end = unbounded) = horse_unavailable, locked through CapacityLock; extras once per stay; a center total_amount replaces the price and skips offers and coupons. Other types are not bookable.

## Cancellation cutoff, refund and credit
Only Client cancellers are bound by reservations.client_cancellation_cutoff_hours. Cancelling refunds min(item total, refundable) through the gateway for online payments (abort the cancel if the refund fails), flags manual payments refund manual_required, gives no refund for cash/pending, and restores one package credit.

## Reschedule keeps service and price
Reschedule re-runs the full booking rules with ignoreReservationId, keeps the same service, and must not change the subtotal (reschedule_price_changed); a different price means cancel and rebook. Append the previous slot to metadata.reschedules.

## Package and membership bookings
BookFromPackage locks the ClientPackage, enforces a trainer-bound package's trainer, creates the reservation Confirmed at price 0, and decrements remaining_sessions (0 = Exhausted). Membership visits require a usable membership before expires_at and a daily count (under lock) within max_visits_per_day; they are free and Confirmed.

## Packages and memberships activate on PurchaseConfirmed
Never sell packages or memberships cash_on_arrival. They are created Pending (membership packages at price 0) and activate only on PurchaseConfirmed, with expires_at counted from activation (+servicePackage.duration / +membership duration months). Listeners are idempotent and only touch still-pending records.

## Checkout rules
Checkout only pending, non-package, total > 0 reservations of one client and one center without an existing purchase item. Cash on arrival needs enable_cash_on_arrival on each service and confirms immediately; online leaves them pending. Total <= 0 confirms with no purchase. Booking and checkout share one transaction.

## Unpaid holds are released, late payments refunded
Pending online purchases older than unpaid_hold_minutes are cancelled only if, re-checked under lock, they have no processing/succeeded/refunded payment; pending payments stay open. A payment that succeeds on an already-cancelled purchase is refunded automatically.

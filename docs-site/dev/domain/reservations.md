# Reservations

The **Reservations** module books slots, enforces capacity and runs the reservation lifecycle, including packages and memberships.

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
| Others | Not bookable. |

## Cancellation

Only **client** cancellers are bound by `reservations.client_cancellation_cutoff_hours`. Cancelling:

- refunds `min(item total, refundable)` through the gateway for online payments, and **aborts the cancel if the refund fails**
- flags manual payments `refund_manual_required`
- gives no refund for cash or pending payments
- restores one package credit

## Reschedule

Re-runs the full booking rules with `ignoreReservationId`, keeps the same service, and must not change the subtotal (`reschedule_price_changed`). A different price means cancel and rebook. The previous slot is appended to `metadata.reschedules`.

## Packages and memberships

- `BookFromPackage` locks the `ClientPackage`, enforces a trainer-bound package's trainer, creates the reservation **Confirmed at price 0**, and decrements `remaining_sessions` (0 means `exhausted`).
- Membership visits need a usable membership before `expires_at` and a daily count (under lock) within `max_visits_per_day`. They are free and Confirmed.
- Packages and memberships are **never sold as cash on arrival**. They are created `pending` (membership packages at price 0) and activate on `PurchaseConfirmed`, with `expires_at` counted from activation (`servicePackage.duration` or the membership duration in months). Listeners are idempotent and only touch records still pending.

## Checkout

Checkout accepts only `pending`, non-package reservations with total > 0, of **one client and one center**, without an existing purchase item.

- Cash on arrival needs `enable_cash_on_arrival` on every service and confirms immediately.
- Online payment leaves the reservations `pending`.
- A total ≤ 0 confirms with no purchase.
- Booking and checkout share one transaction.

## Unpaid holds

`reservations:release-unpaid` cancels pending online purchases older than `unpaid_hold_minutes`, but only if, re-checked under lock, they have no `processing`, `succeeded` or `refunded` payment. Pending payments stay open. A payment that succeeds on an already-cancelled purchase is **refunded automatically**.

## Client endpoints

`reservations` (list, show, create, `quote`, `cancel`), `bookings`, `packages` and `memberships` (list, `quote`, buy), `packages/{package}/reservations`, `memberships/{membership}/visits`.

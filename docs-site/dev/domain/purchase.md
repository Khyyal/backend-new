# Purchases and payments

The **Purchase** module records what a client buys and how it is paid. It also computes the platform fee.

## Concepts

| Concept | Notes |
|---|---|
| **Purchase** | One checkout. Status `pending`, `confirmed`, `cancelled`, `completed`. Has items. |
| **Purchase item** | `total = unit_price × quantity − discount + tax`. |
| **Payment** | One **attempt**. Status `pending`, `processing`, `succeeded`, `failed`, `cancelled`, `expired`, `refunded`. |
| **Adjustment** | A promotion or coupon discount applied to a purchase. See [Promotions](./promotions). |
| **PurchaseFee** | The platform fee snapshot for a center-merchant purchase. |

Purchase `source` is `client`, `center` or `system`. `channel` is `khyyal_app`, `center_app`, `khyyal_web`, `center_web` or `center_dashboard`. Payment methods are `online`, `cash_on_arrival` and `manual`.

## State changes

| Entity | Allowed transitions | Only through |
|---|---|---|
| Purchase | `pending → confirmed`, `pending\|confirmed → cancelled`, `confirmed → completed` | `PurchaseStateService` |
| Payment | `pending → processing`, `pending\|processing → succeeded\|failed\|cancelled\|expired` | `PaymentStateService` |

- Same-status transitions are a no-op.
- `succeeded` is final except for refunds through `recordRefund`: cumulative, amount ≤ remaining, and status `refunded` only when fully refunded.
- Gateways return a `PaymentStatus` and never write `Payment.status`.

## Payments are append-only attempts

A retry is a **new** Payment row. Never revive failed or expired payments, and never delete abandoned pending ones. A purchase is confirmed only when its succeeded payments sum to at least `total_amount`.

Item and payment amounts must be > 0. Manual payment is allowed only when the source is `center`.

## Pricing order

```
line subtotals → promotions / coupon → total = subtotal − discount + tax → platform fee
```

The **fee is what the center owes the platform**. It never changes what the client pays.

- Fee base = `total_amount + sum(adjustment.amount − center_share_amount)`.
- Fee = `intdiv(base × bps, 10000) + fixed`, raised to the minimum, cut to the maximum, capped at the base. A base ≤ 0 means a fee of 0.
- Rule resolution picks the first **active** `FeeRule` in this order: center+channel, platform+channel, center fallback, platform fallback. There is one rule per `(center, channel)`.
- Write exactly **one `PurchaseFee` per center-merchant purchase**, even when it is 0. Reverse it (`reversed_at`) on cancel or full refund; partial refunds keep it. Never recompute or delete fee rows.
- Only system users with `fees.manage` manage fee rules (`admin/fee-rules`).

## Channel resolution

Client requests take the channel from the `X-Channel` header and may not send `center_dashboard`. The center dashboard uses `center_dashboard`. System users get `null`. `center_app` and `center_web` fall back to `null` unless the center has `custom_site_enabled`.

## Gateways and webhooks

| Rule | Detail |
|---|---|
| Never trust the browser | Do not mark a payment succeeded from a callback. Call `verify()` (`POST clients/payments/{payment}/verify`, `POST centers/{center}/payments/{payment}/verify`), which syncs server-side and checks the amount. |
| Units | **Tamara takes major units** (divide by 100); **Moyasar takes halalas**. |
| Reference | Use the Payment id as the provider reference. |
| Secrets | No card data reaches the backend; redact secrets in logs; retry only `initialize`. |
| Boundary | Gateway code must not reference Reservation or Subscription. |
| Webhooks | Deduplicate on `(gateway, external_event_id)`, recording the attempt in its own transaction first. Already processed is a no-op; on failure set `failed_at` and rethrow. Handlers only map an event to `{payment_order_id, status}`; pending results do nothing. |

### Provider status mappings

| Provider | Mapping |
|---|---|
| Moyasar | The failure event is spelled `payment_faild` **on purpose** (do not fix it). `authorized` means Processing. `refunded` and `verified` events change nothing. |
| Tamara | `authorised`/`captured` = Succeeded; `approved`/`new` = Processing; `declined` = Failed; `canceled`/`cancelled` = Cancelled; `expired` = Expired. Refund events change nothing. |

## Admin and center views

`admin/purchases` and `centers/{center}/purchases` list and show purchases and export them. Admins also get `admin/purchases/{purchase}/financials`. Center users see purchases according to their [permissions](../conventions/permissions).

`admin/purchases` and its export take `sort` (`AdminPurchaseQuery::SORTS`): `created_at` or `total_amount`, `-` for descending; newest first without it. See [List sorting](../conventions/coding#list-sorting).

# Promotions and coupons

The **Promotions** module owns discounts: promotions, campaigns, coupons, the discount engine and the redemption ledger.

## The model

| Concept | What it is |
|---|---|
| **Campaign** | A marketing umbrella that groups promotions and can be featured on the homepage |
| **Promotion** | A discount rule |
| **Coupon** | A code that unlocks a promotion that requires one |
| **Redemption** | An immutable ledger row per purchase and promotion |

## Promotion fields

| Field | Values |
|---|---|
| `scope` | `item` (per line) or `order` (the whole purchase) |
| `discount_type` | `percentage` (basis points: 1250 = 12.5%), `fixed_amount`, `fixed_price` |
| `max_discount_amount` | Cap on the discount across lines |
| `min_subtotal_amount` | Minimum spend; promotions with it are ignored for display prices |
| `stacking` | `exclusive` or `stackable` |
| `funded_by` | `platform`, `center` or `shared` (with `center_share_bps`) |
| `status` | `draft`, `active`, `paused`, `archived` |
| Schedules | Optional weekly windows |
| Targets | Include or exclude targets |

## Scope and funding

Promotions apply only when a **Client buys from a Center**, and a center promotion never touches another center's lines. A promotion is **live** when it is active, `starts_at <= now < ends_at`, and its weekly schedule allows it.

The center API forces `center_id = route center` and `funded_by = center`. Only admins set funding, campaign or platform scope. Shared funding gives the center `intdiv(amount × center_share_bps, 10000)`.

## Lifecycle

New promotions start as **draft**. Nothing returns to draft; **archived** is read-only. Once a promotion appears in `purchase_adjustments`, its scope, discount type and value, max discount and targets are **frozen**: archive it and create a new one to change them.

## How discounts combine

`DiscountEngine` applies:

- At most **one item offer per line**: the largest amount, then the higher priority, then the lowest id.
- At most **one order offer**.
- An **exclusive item winner** blocks non-exclusive order offers. An **exclusive order offer** replaces item discounts only if it is larger. Stackable order offers apply after item discounts.
- A line discount never exceeds its subtotal. `max_discount_amount` caps a promotion across lines.
- Order discounts are split across lines by **largest remainder**, so they add up exactly.

## Coupons

Checks run in the fixed order of `PromotionResolverService`. Failures that would reveal whether a code exists all report `invalid`. The coupon's promotion **competes with automatic offers** and returns `coupon_not_better` if it loses. Checkout throws `CouponRejectedException` for a coupon that was sent but not applied. A coupon's per-client limit overrides the promotion's. Codes are stored trimmed and upper-cased.

## Usage is consumed only at purchase

Quotes and display prices **never reserve usage**. `RedemptionService` consumes usage with conditional atomic increments inside the purchase transaction, locking promotions, then coupons, then per-client rows in id order; zero affected rows fails closed. Release at most once, on cancel or full refund. The redemption ledger is the source of truth (`promotions:reconcile` reports drift). `PriceChangedException` is thrown if `expected_total_amount` differs. Writes flush the promotions cache version.

## Display prices

`ServicePriceDisplay::forServices($services, $at)` returns the discounted "from" price for services that carry a `start_price`:

```php
[serviceId => ['original' => 15000, 'discounted' => 12750, 'discount_amount' => 2250, 'badge' => '-15%']]
```

It applies the center's live **automatic item** offers to one unit at the start price. Offers with a minimum spend or order scope depend on the whole purchase and are ignored. Services with no applicable offer are omitted.

Used by: homepage curated services, `clients/offers/{promotion}`, center details services and service details (`display_price`).

## Endpoints

| Surface | Endpoints |
|---|---|
| Client | `clients/offers`, `clients/offers/{promotion}` |
| Center | `centers/{center}/promotions` CRUD, `activate`, `pause`, `archive`, `schedules`, `targets`, `coupons`, `coupons/generate`, `redemptions` |
| Admin | The same under `admin/promotions`, plus `campaigns` and coupon assignments |

---
paths:
  - 'modules/Promotions/**'
---

# Promotions

## Discount engine selection and maths
At most one item offer per line (largest amount, then priority, then lowest id) plus at most one order offer. Exclusive item winners block non-exclusive order offers; an exclusive order offer replaces item discounts only if larger; stackable order offers apply after item discounts. A line discount never exceeds its subtotal; max_discount_amount caps a promotion across lines; order discounts split by largest remainder.

## Promotion scope and funding
Promotions apply only when a Client buys from a Center, and a center promotion never touches another center's lines. Live = active, starts_at <= now < ends_at, and weekly schedule allows. The center API forces center_id = route center and funded_by = center; only admins set funding, campaign or platform scope. Shared funding center share = intdiv(amount*center_share_bps,10000).

## Promotion lifecycle and freezing
New promotions start draft; nothing returns to draft; archived is read-only. Once a promotion appears in purchase_adjustments, its scope, discount type/value, max discount and targets are frozen.

## Coupon checks
Run coupon checks in the fixed order of PromotionResolverService; existence-revealing failures all report invalid. The coupon promotion competes with automatic offers and returns coupon_not_better if it loses; checkout throws CouponRejectedException for a sent-but-unapplied coupon. Coupon per-client limit overrides the promotion's. Codes are stored trimmed and upper-cased.

## Usage is consumed only at purchase
Quotes and display prices never reserve usage. Consume via RedemptionService conditional atomic increments inside the purchase transaction, locking promotions, then coupons, then per-client rows in id order; zero affected rows fails closed. Release at most once on cancel or full refund. The redemption ledger is the source of truth. Throw PriceChangedException if expected_total_amount differs. Flush the promotions cache version on writes.

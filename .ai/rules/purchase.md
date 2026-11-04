---
paths:
  - 'modules/Purchase/**'
---

# Purchase

## Purchase status changes only via PurchaseStateService
Allowed: pending->confirmed, pending|confirmed->cancelled, confirmed->completed. Never write purchase status directly.

## Payment status changes only via PaymentStateService
Allowed: pending->processing, pending|processing->succeeded|failed|cancelled|expired; same-status is a no-op. Succeeded is final except refunds through recordRefund (cumulative, amount <= remaining, status refunded only when fully refunded). Gateways return a PaymentStatus and never write Payment.status.

## Payments are append-only attempts
A retry is a new Payment row; never revive failed/expired payments or delete abandoned pending ones. A purchase is confirmed only when its succeeded payments sum to at least total_amount.

## Purchase totals and payment methods
item.total = unit_price*quantity - discount + tax; purchase totals are sums of item values; item and payment amounts must be > 0. Manual payment is only allowed when source is center.

## Pricing order and platform fee
Order: line subtotals -> promotions/coupon -> total = subtotal - discount + tax -> platform fee. The fee is what the center owes the platform and never changes what the client pays. Fee base = total_amount + sum(adjustment.amount - center_share_amount); fee = intdiv(base*bps,10000) + fixed, raised to min, cut to max, capped at base; base <= 0 means 0.

## Fee rule resolution and snapshot
Resolve the first active FeeRule: center+channel, platform+channel, center fallback, platform fallback; one rule per (center, channel). Write exactly one PurchaseFee per center-merchant purchase, even when 0. Reverse it (reversed_at) on cancel or full refund; partial refunds keep it. Never recompute or delete fee rows. Only system users with ManageFees manage rules.

## Purchase channel resolution
Client requests take the channel from X-Channel and may not send center_dashboard; the center dashboard is CenterDashboard; system users get null. center_app/center_web fall back to null unless the center has custom_site_enabled.

## Webhooks are idempotent translators
Dedupe webhooks on (gateway, external_event_id), recording the attempt in its own transaction first; already processed = no-op; on failure set failed_at and rethrow. Handlers only map an event to {payment_order_id, status}; pending results do nothing.

## Gateway boundary rules
Never mark a payment succeeded from a browser callback; call verify(), which syncs server-side and checks the amount. Tamara takes major units (/100), Moyasar takes halalas. Use the Payment id as the provider reference. No card data reaches the backend; redact secrets in logs; retry only initialize. Gateway code must not reference Reservation or Subscription.

## Provider status mappings are fixed
Moyasar: the failure event is spelled payment_faild on purpose (do not fix), authorized means Processing, refunded/verified events change nothing. Tamara: authorised/captured = Succeeded, approved/new = Processing, declined = Failed, canceled/cancelled = Cancelled, expired = Expired, refund events change nothing.

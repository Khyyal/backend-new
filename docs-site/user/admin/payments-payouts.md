# Purchases, fees and payouts

## Purchases

**Purchases** lists everything clients bought, across all centers. Open one to see its items, payments, discounts and **financials** (fee, platform subsidy and what the center earns). You can export the list.

| Purchase status | Meaning |
|---|---|
| **Pending** | Created, not paid or confirmed yet |
| **Confirmed** | Paid in full (or cash on arrival accepted) |
| **Completed** | Fulfilled |
| **Cancelled** | Stopped; refunds follow the rules below |

A purchase becomes confirmed only when its successful payments add up to the full total. A failed payment can be retried; each try is a new payment record.

## Platform fees

The **platform fee** is what a center owes the platform on a sale. It does **not** change what the client pays. Manage fee rules under **Fees** (you need the fee permission).

A rule can be percentage, fixed amount, or both, with a minimum and maximum. The first matching rule wins, in this order: the center's rule for the channel, the platform rule for the channel, the center's fallback, the platform fallback. Each channel (client app, center app, web, center dashboard) can have its own rule.

The fee is recorded once per sale and **reversed** if the purchase is cancelled or fully refunded. A partial refund keeps the fee.

## Center ledger and payouts

Every online payment adds to the center's **ledger**. After a hold period it becomes available. Fees, refunds and discounts funded by the platform adjust the balance. **Cash and manual payments stay with the center** and are not paid out.

Once a week (Sunday, 00:00 UTC) the platform creates one **pending payout** per center for its available balance. A center is skipped when its balance is zero or negative, or when it has **no bank account** set; its balance carries to the next run.

Under **Payouts** you can open a payout, **mark it paid** after transferring the money (the center is notified), or **cancel** it. You can export a center's ledger from the center page.

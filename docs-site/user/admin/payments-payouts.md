# Purchases, fees and payouts

## Purchases

**Purchases** lists everything clients bought, across all centers. Open one to see its items, payments, discounts and **financials** (fee, platform subsidy and what the center earns). You can sort it by **Total** or **Date** and export the list. Each purchase shows its 6-character **code**, the one the client and the center see.

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

Once a week (Sunday, 00:00 UTC) the platform creates one **pending payout** per center for its available balance. A center is skipped when its balance is zero or negative; its balance carries to the next run. A center with **no bank account** set still gets a payout, with empty bank details, so get the account from the center before transferring.

Under **Payouts** you can open a payout, **mark it paid** after transferring the money (the center is notified), or **cancel** it. You can sort payouts by **Amount**, **Entries** or **Period end**, and a center's ledger by **Amount** or **Payable from**. You can export a center's ledger from the center page.

## Payout invoices

On a payout's page, **Print invoice** opens a PDF of the payout in **Arabic** or **English**. It shows the invoice number (`PO-` and the payout number), the dates, the status, the center, its bank details as they were when the payout was created, totals per entry type, every ledger entry, and the total to transfer in SAR. You can print a payout in any status; a cancelled payout is marked **CANCELLED** across the page.

**Payouts → Invoice template** controls how every invoice looks: the logo (JPEG or PNG), the company name and address in both languages, the VAT and CR numbers, contact email and phone, the accent color, a note above and below the figures, and whether to show the bank details, the summary, the entry list and the signature lines. The preview beside the form updates as you edit, in either language, using sample figures. Nothing changes until you save, and the figures always come from the payout itself. You need the payouts permission.

# Settlements and payouts

The **Settlements** module tracks what the platform owes each center and pays it out.

## The center ledger

`CenterLedgerEntry` rows are **appended**, never edited. Types:

| Type | Meaning |
|---|---|
| `payment` | An online payment credited to the center |
| `refund` | A refund debited on an online payment |
| `platform_fee` | The platform fee debited |
| `fee_reversal` | The fee taken back (cancel or full refund) |
| `platform_subsidy` | A platform-funded discount credited to the center |
| `subsidy_reversal` | The subsidy taken back |

`SyncPurchaseLedger` brings a center purchase's entries in line with its current state **by appending the difference**. Running it again, or for events handled out of order, changes nothing.

- Online payments are credited and become **available after a hold period**. Refunds on them are debited.
- **Cash and manual payments stay with the center**; they never enter the ledger as credits.
- While the purchase is `confirmed` or `completed` and its fee is not reversed, the platform fee is debited and the platform-funded discount is credited. Otherwise both are taken back.

## Payouts

`CenterPayout` status: `pending`, `paid`, `cancelled`.

`purchase:generate-payouts` runs **weekly on Sunday at 00:00** (or manually with `--cutoff=`). `GenerateCenterPayouts` creates **one pending payout per center** for its unpaid ledger entries that are available at the cutoff:

- Debits net against credits.
- A center whose net is zero or negative keeps its entries for the next run.
- A center with **no bank account** (`payout_iban`) is skipped with a warning and keeps its entries.
- A center already paid out for the same cutoff is skipped.

A system user with `payouts.manage` marks a payout paid or cancels it. `CenterPayoutPaid` fires an event that notifies the center.

## Endpoints

| Surface | Endpoints |
|---|---|
| Center | `centers/{center}/balance`, `payout-account` (set by the primary user), `payouts`, `payouts/{payout}`, ledger-related exports |
| Admin | `admin/payouts`, `admin/payouts/{payout}`, `payouts/{payout}/mark-paid`, `payouts/{payout}/cancel`, `admin/centers/{center}/ledger`, `ledger/export` |

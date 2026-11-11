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

## Payout invoices

`GET admin/payouts/{payout}/invoice?locale=ar|en` (default `ar`) streams a PDF that `PayoutInvoiceRenderer` builds from the Blade view `settlements::invoices.payout` with **mPDF**. mPDF handles Arabic shaping and right-to-left layout, and a new instance is created for each render (Octane). The invoice number is `PO-` plus the zero-padded payout id. Money is formatted from halalas without floats, and dates are in UTC. A cancelled payout gets a watermark. Labels come from `lang/{en,ar}/invoice.php`.

The layout is the singleton `PayoutInvoiceTemplate` (one row created by its migration; read it with `current()`):
- en/ar translatable company name, address, `header_note` and `footer_note`.
- Tax and CR numbers, email, phone and `accent_color` (`#RRGGBB`).
- Four `show_*` section flags.
- A `logo` media collection (single JPEG/PNG file).

Notes are cleaned with `LegalHtmlSanitizer`. Saving goes through `PayoutInvoiceTemplateService`, and `applyAttributes()` replaces each translatable field's locales so a cleared locale is removed. The logo is a temporary upload promoted with `TemporaryMediaService`; `null` removes it. `POST admin/payout-invoice-template/preview` renders the unsaved body (plus `locale` and an optional `payout_id`, otherwise in-memory sample data) and saves nothing. The template endpoints authorize `CenterPayoutPolicy::manageInvoiceTemplate` (`payouts.manage`).

## Endpoints

| Surface | Endpoints |
|---|---|
| Center | `centers/{center}/balance`, `payout-account` (set by the primary user), `payouts`, `payouts/{payout}`, ledger-related exports |
| Admin | `admin/payouts`, `admin/payouts/{payout}`, `payouts/{payout}/mark-paid`, `payouts/{payout}/cancel`, `payouts/{payout}/invoice`, `admin/payout-invoice-template` (show, update, `preview`), `admin/centers/{center}/ledger`, `ledger/export` |

Admin lists take `sort`, `-` for descending, newest first without it ([List sorting](../conventions/coding#list-sorting)): `admin/payouts` accepts `CenterPayoutController::SORTS` (`created_at`, `amount`, `period_end`, `entries_count`); the ledger and its export accept `CenterLedgerQuery::SORTS` (`created_at`, `amount`, `available_at`).

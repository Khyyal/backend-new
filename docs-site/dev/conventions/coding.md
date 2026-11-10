# Coding conventions

## Money, percentages and dates

- Amounts are **integer halalas** (1/100 SAR). Never floats or decimals.
- Percentages are **basis points** (10000 = 100%), applied with `intdiv` (floor).
- The app runs in **UTC**. Weekdays use Carbon `dayOfWeek`, where **0 = Sunday**. End of day in schedules is stored as `24:00:00`.
- Third-party boundaries convert explicitly: Moyasar takes halalas, Tamara takes major units (divide by 100).

## Translatable content

Translatable fields are JSON keyed by `en` and `ar`. Every new lang file (notifications especially) needs both.

## PHP style

- Curly braces on every control structure.
- Constructor property promotion; explicit return types and parameter types everywhere.
- Enum cases in TitleCase.
- PHPDoc over inline comments, with array shapes where useful.
- Run `vendor/bin/pint --dirty --format agent` before finishing.

## Creating files

Use `php artisan make:*` with `--no-interaction`, then move or adjust to the module's structure. Follow the sibling files in the same module for naming and layout.

## Status changes

Never write a status column directly:

| Entity | Only change it through |
|---|---|
| Reservation | `ReservationStateService` |
| Purchase | `PurchaseStateService` |
| Payment | `PaymentStateService` |

Each transition locks the row, re-checks the current status, and fires its event **after commit**.

## Events and notifications

- Fire domain events inside `DB::afterCommit`.
- Listeners that handle money or notifications are queued with `ShouldHandleEventsAfterCommit`.
- Every notification extends `AppNotification` and is sent through `NotificationServiceContract`.

## Client media

Attach client-supplied media only through `TemporaryMediaService::promote()` or `promoteMany()`. Never pass client ids to `MediaService::attach()`.

## List sorting

Admin lists that can be sorted take one `sort` query parameter: a key for ascending, `-key` for descending (`?sort=-amount`). Each list keeps its accepted values in a `SORTS` constant (validated with `Rule::in`) and applies them with `Modules\Support\Support\ListSort::apply()`, which maps keys to columns, adds the primary key in the same direction as a tiebreaker, and falls back to the list's default order (newest first) when `sort` is absent. A list and its export share one query, so the export follows the same sort.

## API documentation attributes

Controllers and resources carry Scribe attributes (`#[Endpoint]`, `#[ResponseField]`, `#[ResponseFromApiResource]`). Update them when a response changes so the endpoint reference stays correct.

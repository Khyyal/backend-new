# API overview

Almost all endpoints are under `/api/v1`. The exception is **Billing** plans and center subscriptions, which live under `/api/admin/*` and `/api/center/*` (see [Billing](../domain/billing)). The exact request and response of every endpoint is in the <a href="/docs" target="_self">endpoint reference</a> (Scribe), generated from the code.

## Conventions

| Topic | Rule |
|---|---|
| Format | JSON in, JSON out. Resources wrap their payload in `data`; lists add `meta` for pagination. |
| Errors | JSON for `api/*` and any request that expects JSON. Validation errors are 422 with an `errors` object. |
| Language | Send `Accept-Language: en` or `ar` on client browse endpoints. Translatable fields return the requested language. |
| Money | Integer **halalas**. `15000` is 150.00 SAR. |
| Dates | UTC, ISO 8601. Weekdays use `0 = Sunday`. |
| Prices shown to clients | `start_price` (lowest option) and `display_price` (`null` or `{original, discounted, discount_amount, badge}`) |
| Booking errors | 422 with a stable `reason` key. See [Reservations](../domain/reservations). |

## Surfaces

| Prefix | Actor |
|---|---|
| `clients/*` | Client app |
| `centers/*` | Center dashboard and app |
| `admin/*` | Admin panel |

See [API surfaces and guards](../architecture/api-surfaces).

## Documenting a change

Controllers and resources carry Scribe attributes. When a response changes, update `#[ResponseField]` and `#[Endpoint]`, then regenerate with `php artisan scribe:generate`. Behaviour changes also go in these guides; see [Keeping these docs current](../conventions/documentation).

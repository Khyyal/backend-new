# Architecture overview

Khyyal is a **modular monolith**: one Laravel application whose code is split into modules under `modules/`. Modules talk to each other through models, events and a few contracts, never through HTTP.

## Actors and surfaces

| Actor | Guard | Prefix | Used by |
|---|---|---|---|
| Client | `client` | `api/v1/clients/*` | The client app |
| Center user | `center_user` | `api/v1/centers/*` | The center dashboard and center app |
| System user | `system_user` | `api/v1/admin/*` | The admin panel |

See [API surfaces and guards](./api-surfaces).

## How a request flows

1. A route in a module (`modules/<Module>/routes/*.php`), mounted under `api/v1` by the module's service provider.
2. Middleware: guard, `center.scope` for center routes, locale middleware for client routes.
3. A controller that stays thin: a Form Request validates the shape, a policy authorizes, a service does the work.
4. A service or action that holds the business rules, usually inside a database transaction.
5. An API Resource shapes the response.
6. Domain events fire **after commit**; listeners that touch money or send notifications are queued.

## Principles

- **Money is integer halalas**, percentages are basis points (10000 = 100%). Never floats.
- **State changes go through one service** (reservation, purchase and payment status). Never write a status column directly.
- **Cross-module access goes through contracts** where the dependency would otherwise be circular. Example: `Promotions\Contracts\ServicePriceDisplay` is used by Homepage, Centers and Services to show discounted prices.
- **Everything is audited** where it matters (Activitylog), and analytics keep no personal data.
- **Octane**: the app boots once and serves many requests, so no request state in singletons. See [Octane](./octane).

# Billing and subscriptions

The **Billing** module sells **plans** to centers as **subscriptions**. It is separate from client purchases.

## Model

| Concept | Notes |
|---|---|
| **Plan** | `name`, `slug`, `description`, `price`, `billing_interval` (`monthly` or `yearly`), `trial_days`, `status` (`active` or `in_active`), `display_features` |
| **Feature** / **Limit** | The catalog a plan is built from (`PlanFeature`, `PlanLimit`) |
| **Subscription** | A center's subscription to a plan. Status `pending`, `trialing`, `active`, `canceled`, `expired` |

## Subscription lifecycle

Actions are `subscribe`, `cancel`, `renew` and `expire`.

- **Subscribe** only to an `active` plan, with no `active` or `trialing` subscription to the same plan, holding a lock on the plan.
- Status is `trialing` when `trial_days > 0`, otherwise `active`.
- `ends_at = starts_at + one interval` (`addMonthsNoOverflow`).
- Only the **owner** cancels `active` or `trialing` subscriptions.
- **Renew** creates a new row from the previous `ends_at`, with no trial.
- Billing has **no VAT, invoices or proration**.
- `billing:release-unpaid` runs every 5 minutes and releases unpaid subscription holds.

## Endpoints

::: warning Not versioned
The Billing routes are **not** under `/api/v1`. They live under `/api/<actor>/...`.
:::

| Surface | Endpoints |
|---|---|
| Admin catalog | `api/admin/plans` (CRUD, `PATCH {plan}/status`, attach and detach `features` and `limits`), `api/admin/features` and `api/admin/limits` (CRUD). Permissions `plans.view_any`, `plans.create`, `plans.update`, `plans.delete`; the feature and limit catalog needs `plans.manage_catalog`. |
| Admin subscriptions | `api/v1/admin/subscriptions` (list, show), `api/v1/admin/centers/{center}/subscription` (show, change). Needs `subscriptions.view_any` and `subscriptions.change`. The list takes `sort` (`AdminSubscriptionQuery::SORTS`): `starts_at` or `ends_at`, `-` for descending; newest first without it. |
| Center | `api/center/plans` (catalog, list and show), `api/center/subscriptions` (list, `current`, subscribe, cancel with `DELETE {subscription}`) |

Subscription payments use the same gateways as purchases; gateway code must not reference Subscription. See [Purchases and payments](./purchase).

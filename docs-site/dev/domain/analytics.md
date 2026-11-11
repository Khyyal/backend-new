# Analytics

The **Analytics** module serves dashboards from **rollups** built by `analytics:build` (daily at 00:30, `onOneServer`).

## Admin analytics

Requires `analytics.view`. Sections (`AnalyticsSection`): overview, revenue, bookings, payments, clients, centers, promotions, subscriptions and engagement.

| Endpoint | Section |
|---|---|
| `admin/analytics/overview` | Overview |
| `admin/analytics/revenue` | Revenue |
| `admin/analytics/bookings` | Bookings |
| `admin/analytics/payments` | Payments |
| `admin/analytics/clients` | Clients |
| `admin/analytics/centers` | Centers |
| `admin/analytics/promotions` | Promotions |
| `admin/analytics/subscriptions` | Subscriptions |
| `admin/analytics/engagement` | Client engagement |
| `admin/analytics/home-content` | [Homepage](./homepage) content events |
| `admin/analytics/export` | Export |

### Financial baseline corrections

`BuildDailyCenterStats` adds each **confirmed** [financial baseline](./settlements#financial-baselines) to the day of its `cutoff_at`: `net_fee`, `gmv` and `refunds` move by `target - migrated` for every target finance gave. The whole correction lands on that one day (it can be negative), so totals over a range that includes it match finance. `baseline_adjustment` ledger entries are not revenue and are ignored. `RebuildBaselineCutoffDay` rebuilds the cutoff day when a baseline is confirmed, unless it is today.

## Center analytics

`GET centers/{center}/analytics` (permission `analytics.view`) shows the center's own numbers.

- Revenue counts only **Confirmed** and **Completed** purchases where the center is the merchant, compared with the previous period of the same length.
- Analytics are **center-wide**; they are not narrowed by purchase visibility.

## Engagement

Clients report engagement through `POST clients/engagement/events`. Events are aggregate-only: no personal data is stored.

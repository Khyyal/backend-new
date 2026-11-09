# Modules

All modules live in `modules/`. Each one registers itself through its `*ServiceProvider`.

| Module | Owns | Page |
|---|---|---|
| **Support** | System users, roles, cities, tags, OTP, devices, media, activity log, API docs access | [Support](../domain/support) |
| **Clients** | Client accounts, profile, favorites, ratings, client auth | [Clients](../domain/clients) |
| **Centers** | Centers, center users and permissions, visibility, site theme, ratings | [Centers](../domain/centers) |
| **Services** | The eight service types, price options, schedules, blocks, packages, trainers | [Services](../domain/services) |
| **Reservations** | Booking, availability, capacity, packages, memberships, cancellation | [Reservations](../domain/reservations) |
| **Purchase** | Purchases, payments, gateways, webhooks, platform fees | [Purchases](../domain/purchase) |
| **Promotions** | Promotions, campaigns, coupons, the discount engine, redemptions | [Promotions](../domain/promotions) |
| **Billing** | Plans, features, limits, center subscriptions | [Billing](../domain/billing) |
| **Settlements** | Center ledger and payouts | [Settlements](../domain/settlements) |
| **Homepage** | Home sections, banners, curated items, content analytics | [Homepage](../domain/homepage) |
| **Notifications** | In-app and push notifications, campaigns, WhatsApp templates | [Notifications](../domain/notifications) |
| **Analytics** | Admin dashboards and center analytics | [Analytics](../domain/analytics) |
| **Exports** | Asynchronous exports and downloads | [Exports](../domain/exports) |

## Structure of a module

```
modules/Services/
  database/        migrations, factories, seeders
  lang/            en and ar strings (every new key needs both)
  routes/          admin.php, center.php, client.php ...
  src/
    Enums/  Models/  Policies/  Services/  Http/{Controllers,Requests,Resources}/
    ServicesServiceProvider.php
  tests/           Feature and Unit (Pest)
```

## Dependencies

The main direction of dependency is: Support → Clients and Centers → Services → Reservations → Purchase, with Promotions, Settlements, Billing, Homepage, Notifications, Analytics and Exports reading from the modules above them. Where a lower module needs data from a higher one, it uses a contract or an event.

Reservations and Billing both create purchases; the payment gateways in Purchase must not reference Reservation or Subscription.

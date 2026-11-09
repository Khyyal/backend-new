# Notifications

## In-app and push

Every notification extends `AppNotification` (type, `translationKey`, data) and is sent through `NotificationServiceContract`, **queued** to the `database` channel and `LocalizedFcmChannel`.

- The **inbox stores every locale**; push renders per device locale.
- Platform-event notifications go to `SystemUser::notifiableAdmins()`.
- Every new lang file needs both `en` and `ar` entries.

Each actor reads its inbox at `…/notifications` (list, `unread-count`, `mark-as-read`, `mark-all-as-read`) under `clients`, `centers` and `admin`.

## Campaigns

Admin-only (`notification_campaigns.manage`). A campaign targets **Clients** or **Centers** (a Centers audience reaches `Center::notifiableUsers()`) over the **push** or **whatsapp** channel.

### Lifecycle

```
draft ─► scheduled ─► sending ─► sent
  │          │            └────► failed
  └──────────┴──► cancelled (terminal)
```

- Edit content and targets only while `draft` or `scheduled`.
- A `sending` campaign cannot be deleted.
- Start only through `CampaignService::sendNow()` (a conditional update); only `markFinished` ends `sending`.
- `notifications:dispatch-due-campaigns` runs every minute and sends scheduled campaigns that are due.

### Targeting and delivery

| Rule | Detail |
|---|---|
| Target mode `all` | No target rows |
| Target mode `selected` | 1–5000 existing ids of the audience model |
| Push | Needs both `en` and `ar` title and body |
| WhatsApp | Template parameters must equal the template's variable count |
| Retries | WhatsApp writes one delivery row per recipient and skips existing ones, so retries never resend |

## Endpoints

`admin/notification-campaigns` (CRUD, `schedule`, `send`, `cancel`, `duplicate`, `deliveries`) and `admin/whatsapp-templates` (CRUD, `whatsapp_templates.manage`).

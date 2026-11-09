# Configuration

Copy `.env.example` to `.env` and set the values below. Only the keys that matter for this platform are listed; the rest are standard Laravel.

## Application

| Key | Purpose |
|---|---|
| `APP_URL` | Public URL. Used in links and media URLs. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | Default language (`en` or `ar`). Client requests override it with `Accept-Language`. |
| `QUEUE_CONNECTION` | Queues are required: notifications, exports, WhatsApp and money listeners run queued. |
| `CACHE_STORE` | Used for the home payload and the promotions cache. Use a store shared by all workers in production. |
| `FILESYSTEM_DISK`, `AWS_*` | Media and export storage. |

## Seeded super admin

| Key | Purpose |
|---|---|
| `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD` | The first system user, created by the seeder with all permissions. |

## Payments

| Key | Purpose |
|---|---|
| `PURCHASE_GATEWAY` | Which gateway the checkout uses. |
| `MOYASAR_PUBLISHABLE_KEY`, `MOYASAR_SECRET_KEY`, `MOYASAR_WEBHOOK_SECRET` | Moyasar credentials. Webhooks arrive at `POST /api/v1/webhooks/moyasar`. |
| `MOYASAR_APPLE_PAY_*` | Apple Pay options for Moyasar. |
| `TAMARA_API_TOKEN`, `TAMARA_NOTIFICATION_TOKEN` | Tamara credentials. Webhooks arrive at `POST /api/v1/webhooks/tamara`. |

Never log secrets and never send card data through the backend. See [Purchases and payments](../domain/purchase).

## Messaging

| Key | Purpose |
|---|---|
| `FIREBASE_CREDENTIALS` | Service account for push notifications (FCM). |
| `VOXA_*` | WhatsApp provider used by notification campaigns. |
| `MAIL_*` | Outgoing mail. |

## OTP development bypass

Phone login uses one-time codes. A bypass for developers exists, but it works only when `otp.dev.enabled` is on, the environment is `local`, `testing` or `staging`, and the phone number is listed in `OTP_DEV_PHONES`. Never enable it in production. See [Support](../domain/support).

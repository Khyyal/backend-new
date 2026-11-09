# API surfaces and guards

API routes live under `/api/v1`, except Billing plans and center subscriptions, which are unversioned (`/api/admin/*`, `/api/center/*`).

| Surface | Prefix | Guard | Extra middleware |
|---|---|---|---|
| Client | `clients/*` | `auth:client` (some routes are public) | `set.locale.from.accept.language` on browse routes, `track.client.session` on authenticated ones |
| Center | `centers/{center}/*` | `auth:center_user` | `center.scope` |
| Admin | `admin/*` | `auth:system_user` | Policies check `SystemPermission` |
| Shared | `media`, `webhooks/*` | `POST /media` accepts several guards; webhooks are public and signature-checked | |

## Center scope

Every `centers/{center}/*` route needs `center.scope`. It requires the token ability `center-access:{id}` and the center state to be **Active** (only `centers.show` works otherwise). The middleware does **not** check membership, so controllers must still authorize through `CenterPolicy`. Issue center tokens only through `CenterAuthService::issueCenterAccessToken()`.

## Admin mirrors center

Many admin routes mirror the center ones under `admin/centers/{center}/...` (services, reservations, purchases, promotions), so staff can act on behalf of a center. They use the same services and policies, which accept both center users and system users.

## Docs sessions

API documentation (`/docs`) and these guides (`/guide`) use a **session** guard, `system_user_web`, not a token. It is separate from `system_user`.

## Webhooks

`POST /api/v1/webhooks/moyasar` and `/tamara` are idempotent: each event is deduplicated on `(gateway, external_event_id)`. See [Purchases and payments](../domain/purchase).

# Roles and permissions

## One guard per actor

Client routes use `auth:client`, center users `auth:center_user` with `center.scope`, system users `auth:system_user`.

## System users

System users have roles made of **`SystemPermission`** values such as `centers.view_any`, `promotions.activate`, `fees.manage`, `payouts.manage`, `payouts.baseline` (set a migrated center's financial baseline), `analytics.view`, `legal_documents.manage`, `otps.create` (issue a login OTP for a client or center user) and `api_docs.view`.

- Add a new admin capability as a new `SystemPermission` case named `resource.action`.
- Never edit or delete protected `SystemRole`s.
- Every admin policy denies **inactive** system users in `before()`.

## Center users

Center users belong to centers through an assignment, and hold **`CenterUserPermission`** values:

| Group | Permissions |
|---|---|
| Services, per type | `services.recreation_riding.manage`, `services.visit.manage`, `services.event.manage`, `services.resort.manage`, `services.horse_care.manage`, `services.horse_service.manage`, `services.training.manage`, `services.membership.manage` |
| Purchases, per type | `purchases.<type>.view`, plus `purchases.view_prices`, `purchases.view_other_trainers`, `purchases.view_unassigned` |
| Reservations | `reservations.manage`, `reservations.edit` |
| Other | `users.manage`, `ratings.view`, `blocked_clients.manage`, `promotions.manage`, `analytics.view`, `payouts.view`, `purchases.export`, `clients.export` |

The **primary user** (`is_primary`, role `owner`) implicitly holds every center permission, can never be modified, and must re-enter their password on `POST /centers/{center}/access`. Non-primary managers cannot modify themselves and can only grant permissions they hold. Removing a user revokes their center tokens; a user with no centers left is soft-deleted.

## Shared policies

Policies for shared admin and center endpoints authorize `User|SystemUser`: center users by active assignment plus center permission, system users by `SystemPermission`.

Service authorization depends on the type: `Gate::authorize('manageServices', [$center, ServiceType::X])`. Trainers fall under Training. Center-wide blocks and gender periods need `manageAllServices`.

## Purchase visibility

Non-primary center users see purchases through `CenterPurchaseVisibility`: the type's `purchases.{type}.view`, their own linked trainer always, other trainers' only with `purchases.view_other_trainers`, and trainer-less items only with `purchases.view_unassigned`. Primary and system users see all.

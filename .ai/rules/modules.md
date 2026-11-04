---
paths:
  - 'modules/**'
---

# Modules

## Money is integer halalas, percentages are bps
Store and compute all amounts as integer halalas (1/100 SAR); never floats or decimals. Percentages are basis points (10000 = 100%) applied with intdiv (floor).

## One guard and prefix per actor
Client routes use auth:client under clients/*, center users use auth:center_user + center.scope under centers/{center}/*, system users use auth:system_user under admin/*. Only POST /media accepts multiple guards. Mount module routes under api/v1 from the module ServiceProvider.

## Center-scoped routes need center.scope and a policy check
Every centers/{center}/* route needs the center.scope middleware (token ability center-access:{id}; center state must be Active, only centers.show is allowed otherwise). The middleware does not check membership, so controllers must still authorize via CenterPolicy. Issue center tokens only through CenterAuthService::issueCenterAccessToken().

## Policies serve both center Users and SystemUsers
Shared admin/center endpoints authorize User|SystemUser: center users by active assignment + center permission (primary user has all), system users by SystemPermission. Every admin policy denies inactive system users in before(). Add new admin capabilities as SystemPermission cases (resource.action); never edit or delete protected SystemRoles.

## Domain events fire after commit
Fire state-change events inside DB::afterCommit, and make money/notification listeners queued with ShouldHandleEventsAfterCommit, so nothing reacts to a rolled-back transaction.

## Dates are UTC with Carbon weekday numbering
The app runs in UTC with no per-center timezone. Weekdays everywhere (schedules, resort day prices, trainer overrides) use Carbon dayOfWeek, 0 = Sunday. End of day in schedules is stored as 24:00:00 (Schedule::END_OF_DAY).

## Translatable content is en/ar
Translatable fields are JSON keyed by en and ar. Every new lang file (notifications especially) needs both en and ar entries.

## Notifications extend AppNotification
Every in-app/push notification extends AppNotification (type, translationKey, data) and is sent through NotificationServiceContract, queued to database + LocalizedFcmChannel. The inbox stores every locale; push renders per device locale. Platform-event notifications go to SystemUser::notifiableAdmins().

## Client media goes through temporary uploads
Attach client-supplied media only via TemporaryMediaService::promote()/promoteMany() (owner, expiry, lock, single use; promoteMany is all-or-nothing). Never pass client ids to MediaService::attach(). Report every media failure as one 422 InvalidTemporaryMediaException.

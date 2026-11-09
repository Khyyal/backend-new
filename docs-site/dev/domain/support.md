# Support: OTP, devices and media

The **Support** module holds the shared building blocks.

## OTP

All phone sign-in goes through `OtpVerificationService`.

- Codes are **hashed** and expire after **300 seconds**.
- A new send invalidates older unused codes. A successful verify consumes the code.
- Limits: **5 sends per phone per 15 minutes**, **5 failed verifies per 5 minutes**.
- Use the same phone string for send, verify and account lookup.

### Development bypass

Works only when `otp.dev.enabled` is on, the environment is `local`, `testing` or `staging`, **and** the phone is in `OTP_DEV_PHONES`. Never widen it.

## Devices and push tokens

Register devices through `DeviceService::launch()` and link them on login with `attachTo()`. A device is unique per `(owner, identifier, platform)`. Invalid FCM tokens are **nulled, not deleted**.

## Media

Uploads go to `POST /api/v1/media` (the only endpoint that accepts several guards) and become **temporary uploads**. Attach client-supplied media only through `TemporaryMediaService::promote()` or `promoteMany()` (owner, expiry, lock, single use; `promoteMany` is all-or-nothing). Every media failure is reported as one 422 `InvalidTemporaryMediaException`. `DELETE media/{uuid}` removes an upload.

## System users, roles, cities and tags

Admin CRUD for `system-users` (with `restore`), `system-roles`, `system-permissions` (read), `cities` and `tags`. See [Roles and permissions](../conventions/permissions). Every admin policy denies inactive system users.

## Activity log

`admin/activity-logs` shows the audit trail (`activity_logs.view_any`). Translatable attributes are logged with every locale.

## Documentation access

`/docs` (endpoint reference) and `/guide` (these guides) are protected by a session login at `/docs/login` for active system users with `api_docs.view`. Five failed logins in a minute return 429.

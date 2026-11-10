# Support: OTP, devices and media

The **Support** module holds the shared building blocks.

## OTP

All phone sign-in goes through `OtpVerificationService`.

- Codes are **hashed** and expire after **300 seconds**.
- A new send invalidates older unused codes. A successful verify consumes the code.
- Limits: **5 sends per phone per 15 minutes**, **5 failed verifies per 5 minutes**.
- Use the same phone string for send, verify and account lookup.
- Codes are always **4 digits** (the client verify request requires `digits:4`).

### Admin-issued codes

`issue()` creates a code for support staff to read to a user who is not receiving the SMS. It stores the code hashed like any other, invalidates older unused codes for the phone and returns the plain code once. It sends **no SMS** and skips the user-facing send limit; the admin routes are throttled to 10 per minute instead. It always writes a row, so the issued code also works for development phones.

Endpoints, both behind `otps.create`:

- `POST admin/clients/{client}/otp`, for the client's `phone_number`
- `POST admin/centers/{center}/users/{user}/otp`, for the center user's `phone`. The user must belong to the center, otherwise it returns 404.

### Development bypass

Works only when `otp.dev.enabled` is on, the environment is `local`, `testing` or `staging`, **and** the phone is in `OTP_DEV_PHONES`. Never widen it.

## Devices and push tokens

Register devices through `DeviceService::launch()` and link them on login with `attachTo()`. A device is unique per `(owner, identifier, platform)`. Invalid FCM tokens are **nulled, not deleted**.

Platforms are `ios`, `android` and `web`. Admins (browsers in the panel) register with `POST admin/devices` (`launch()` with the system user as owner, idempotent) and remove one with `DELETE admin/devices/{deviceIdentifier}`. `POST admin/auth/logout` revokes the current token and, when `X-Device-Identifier` (or `device_identifier`) is sent, removes that device too, so pushes to `SystemUser::notifiableAdmins()` stop reaching a signed-out browser.

## Media

Uploads go to `POST /api/v1/media` (the only endpoint that accepts several guards) and become **temporary uploads**. Attach client-supplied media only through `TemporaryMediaService::promote()` or `promoteMany()` (owner, expiry, lock, single use; `promoteMany` is all-or-nothing). Every media failure is reported as one 422 `InvalidTemporaryMediaException`. `DELETE media/{uuid}` removes an upload.

## System users, roles, cities and tags

Admin CRUD for `system-users` (with `restore`), `system-roles`, `system-permissions` (read), `cities` and `tags`. See [Roles and permissions](../conventions/permissions). Every admin policy denies inactive system users.

## Activity log

`admin/activity-logs` shows the audit trail (`activity_logs.view_any`). Translatable attributes are logged with every locale.

## Legal documents

`LegalDocument` holds the privacy policy and terms (`LegalDocumentType`: `privacy_policy`, `terms`) of each app (`LegalAudience`: `client`, `center`). The four rows are created by the migration and only updated; `content` is translatable HTML (en/ar), activity-logged as `legal_document`.

- Admin: `GET admin/legal-documents`, `GET|PUT admin/legal-documents/{audience}/{type}` with `legal_documents.manage`. A `PUT` needs both `content.en` and `content.ar` (max 200,000 characters each). `LegalHtmlSanitizer` (symfony/html-sanitizer) cleans the HTML before saving: safe elements only, no scripts or event handlers, links limited to http(s)/mailto/tel, `rel="noopener noreferrer"` forced on links, and `style` reduced to `text-align`.
- Public page: `GET /legal/{audience}/{type}?lang=en|ar` (`legal.show`, no auth, `web` group) renders `support::legal.show`, a standalone page with `dir="rtl"` for Arabic. Unknown `lang` falls back to `en`; an empty document shows a "not available yet" note. Cached by clients for 5 minutes.
- Apps: `GET client/legal` and `GET center/legal` (no token) return `privacy_policy_url` and `terms_url` in the `Accept-Language` locale (`en`/`ar`, default `en`) for the app to open in a webview.

## Documentation access

`/docs` (endpoint reference) and `/guide` (these guides) are protected by a session login at `/docs/login` for active system users with `api_docs.view`. Five failed logins in a minute return 429.

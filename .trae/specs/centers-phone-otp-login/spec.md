# Centers Module: Phone + OTP Login & Center-Selection Endpoints

## Problem

Primary users of the Centers module currently register via `/api/centers/register` (phone + OTP + password set at registration). There is no login path for already-registered users. The module also has no way for users to list centers they belong to, select a specific center, or obtain a center-scoped access credential after login.

This spec adds:

1. A **Phone+OTP login flow (send/verify) for **existing** registered users only.
2. A center listing and selection endpoints, including a password-gated access endpoint for primary center users.

## Users

- Primary center owners who completed `/register` and need to log in on new devices.
- Non-primary center users (staff/assistants invited to a center) who need OTP-based login (center selection, and primary/non-primary access escalation flows.

## Goals

- Primary users log in with phone + OTP (no password required at login).
- No auto-creation of users from the OTP-login endpoint. If a phone has no matching User, the endpoint explicitly tells the caller to register first, matching the existing 422 validation shape.
- Authenticated users list all centers they have an assignment for (center_user_assignment row).
- Users can view a single center they have access to.
- Primary users on that center re-verify their password to get a center-scoped access credential. Non-primary users get the same credential shape without a password check.

## Non-Goals

- Does NOT modify `/register`, RegisterController, or CenterRegisterService.
- Does NOT modify Center, User, or CenterUser models beyond what is required: one targeted pivot-withPivot fix on User::centers().
- Does NOT reimplement OTP generation/expiry/attempt logic. Uses the existing OtpVerificationService (with targeted modifications required only to surface the verify attempt-exceeded distinction per user confirmation).
- OTP is not a registration mechanism. No User creation in login/verify endpoints.
- Does not change existing Sanctum token model or guard.

---

## Confirmed Open Questions (from user)

| # | Question | Answer | Impact |
|---|---|---|---|
| 1 | Does `verify()` distinguish wrong-code vs attempts-exceeded? | No. Confirmed OtpVerificationService.verify collapses both to `false`; ensureCanVerify() has empty return so verify rate limiter is non-blocking dead code. **User elected to modify OtpVerificationService first before implementing endpoints so we can distinguish and return 429 vs 422. | Requires pre-work change in OtpVerificationService. |
| 2 | What does "role" mean in GET /api/centers per-center payload? | **Spatie role name (string)** resolved per-center via team-scoped PermissionRegistrar (setPermissionsTeamId = center->id then getRoleNames). | Need team-context role resolution in controller/resource. |
| 3 | Non-primary user on POST /centers/{center}/access? | **Skip password check**, return the same response shape as primary (token + center). | Same shape, no password gate. |
| 4 | Duplicate throttle middleware on /phone (send already has internal 5/15m limiter)? | **Add outer throttle anyway** per user's explicit answer. | Route-level throttle in addition to service-internal. |
| 5 | Phone validation rule on login endpoints? | User selected "Other" with text "check if the phone exists". Interpreted as: phone field must exist in `center_users` table (FormRequest-level `exists:center_users,phone` rule combined with string/max). | Use rule `['required', 'string', 'max:32', 'exists:center_users,phone']` with a custom "please register" message for the exists failure. |
| 6 | Non-primary response shape on /access? | Same shape as primary. Design both as `{ token, token_type: "Bearer", center: CenterResource }` (token from issueAppToken, identical for both; password gate only for is_primary). | Consistent JSON envelope. |

## Additional findings reported to user

- **OtpVerificationService::send() signature confirmed**: `public function send(string $phoneNumber, int $ttl = self::DEFAULT_TTL_SECONDS): void` (returns void, optional TTL default 300s). Has internal rate limiter 5/15m phone-based, throws RuntimeException on exceed (currently renders as 500; will be fixed alongside verify changes).
- **OtpVerificationService rate-limiter bug in ensureCanVerify(): empty `return;` on attempts-exceeded means limiter counts attempts but never actually blocks. Fixed as part of required pre-work.
- **User::centers() BelongsToMany missing `is_primary` in withPivot**. Only `status` and `joined_at`. Required to add `is_primary` for the /access endpoint check. This is explicitly required by the spec (endpoint 4 product rule "if pivot.is_primary is true"), so the model change is in scope and minimal.

## Migrations Required

**None.** All required columns already exist:
- `center_users.phone_verified_at` (fillable on User model)
- `center_user_assignment.status`, `.joined_at`, `.is_primary`

---

## Functional Requirements

### FR-1 — Pre-Work: OtpVerificationService distinction + HTTP-safe exceptions

**Scope**: Modify Modules\Support\Services\OtpVerificationService.

- **send()** currently throws plain RuntimeException on too many sends. Map to a proper 429 HTTP response by changing `ensureCanSend()` to throw a Laravel ThrottleRequestsException or an exception with Retry-After seconds.
- **verify()** distinction:
  - `ensureCanVerify()` MUST throw the same 429 exception when rate limiter is exceeded. Remove current empty return. The exception is the signal for 429.
  - `verify()` continues returning `bool` for the code check: true = success; false = wrong/expired/no-otp.
  - Controller-level distinction: exception on attempts-exceeded surfaces as 429; bool-false surfaces as 422 with code-field error.

This is not a reimplementation; it makes the existing attempt-tracking data actionable.

### FR-2 — POST /api/centers/auth/phone — Request OTP

- **Authentication**: none (guest).
- **Middleware**: `api`, route-level throttle (user decision #4) in ADDITION to send()'s internal limiter.
- **Request validation**: FormRequest with field `phone`. Rule matches confirmed answer #5: `['required', 'string', 'max:32', 'exists:center_users,phone']`.
- **Behavior**:
  - If `exists:center_users,phone` fails → standard 422 validation error keyed on `phone` with message telling user to register (matches RegisterController 422 shape via ValidationException).
  - If found → call `OtpVerificationService::send((string) $phone)` with default TTL.
  - On send() rate-limit exception → 429 with Retry-After (exceptions handled via FR-1).
- **Success response**: 200. Body: `{ message, expires_in_seconds }` (mirrors Clients AuthController::login shape). No token, no user data.

### FR-3 — POST /api/centers/auth/phone/verify — Verify OTP + Issue Token

- **Authentication**: none (guest).
- **Middleware**: `api`.
- **Request validation**: FormRequest with `phone` (same rule as FR-2 including `exists:center_users,phone`) and `code` (`['required', 'string', 'digits:6']` matching Clients VerifyOtpRequest pattern for digit length although OTP is actually generated as 4-digit in service—confirm: service generateCode pads to 4 chars. Rule should be `digits:4` not 6. CORRECTION below in Assumptions).
- **Behavior**:
  - Call `OtpVerificationService::verify($phone, $code)`.
  - On `false`: 422 ValidationException keyed on `code`, message e.g. __('otp.invalid').
  - On exception (attempts exceeded): 429 with Retry-After.
  - On `true`:
    - Load the User by phone.
    - If `$user->phone_verified_at` is null → set to `now()` and save (mark verified on first successful OTP login).
    - Issue token via `CenterAuthService::issueAppToken($user, $request)`.
    - Response: 200. Shape: `{ user: UserResource, token: string, token_type: "Bearer" }`. This matches RegisterController::store() response MINUS the `center` key (per spec requirement "same response shape as RegisterController::store() minus the 'center' key").

### FR-4 — GET /api/centers — List User's Centers

- **Authentication**: `auth:sanctum` guard `center_user`.
- **Middleware**: `auth:sanctum`, resolve guard center_user.
- **Query**: For the authenticated user, return every Center with a row in `center_user_assignment` for this user_id (regardless of pivot.status, or filter to ACTIVE? Assumption A-2).
- **Per-center payload keys**: `id`, `name`, `role`.
  - `role` = Spatie role name (string), resolved per-center via team-scoped PermissionRegistrar: set team_id = center.id, then $user->getRoleNames()->first() (or null if none).
  - Note: User::centers() BelongsToMany withPivot must include `is_primary` (currently missing) to enable /access endpoint; listing itself only needs name + id + role via Spatie.
- **Response**: 200. Anonymous ResourceCollection (or `CenterWithRoleResource::collection(...)`) wrapping each center.

### FR-5 — GET /api/centers/{center} — View Single Center

- **Authentication**: `auth:sanctum` guard `center_user`.
- **Authorization**: 403 if the authenticated user has NO `center_user_assignment` row for this center_id (regardless of status—assumption A-3). No password check here.
- **Response**: 200 with `CenterResource` (existing). Standard envelope.

### FR-6 — POST /api/centers/{center}/access — Obtain Center-Scoped Access

- **Authentication**: `auth:sanctum` guard `center_user`.
- **Authorization**: 403 if user has NO assignment row for this center.
- **Branch on pivot.is_primary for this (user, center)**:
  - **`is_primary === true`**:
    - Require a `password` field in the request body. FormRequest validates: `password` = `['required', 'string']`.
    - Verify password against `Hash::check($request->password, $user->password)`. Fail → 422 keyed on `password` with invalid-credentials message.
    - Pass → continue.
  - **`is_primary === false`**:
    - Do NOT require or validate `password`. Skip password check entirely. Continue.
- **Token issuance** (both branches):
  - Issue token via `CenterAuthService::issueAppToken($user, $request)`.
  - Load center relationship.
  - Response shape: `{ token, token_type: "Bearer", center: CenterResource }` for both branches (answer #6 confirmed: same shape).
- **Rationale**: Primary users have elevated permissions hence re-verification requirement; non-primary users already authenticated via OTP login have sufficient trust for the same credential shape without password.

## Non-Functional Requirements

- **NFR-1 Scramble attributes**: Every new public controller method MUST carry `#[Group]` (on the controller class level for Center Auth and Center Profile/Center) and `#[Response]` attributes for every documented status code.
- **NFR-2 FormRequest**: All validation uses FormRequest classes. No inline `$request->validate()`.
- **NFR-3 Resource classes**: All JSON responses use JsonResource / ResourceCollection classes. No naked arrays except for the wrapping envelope that include the token (for consistency with RegisterController, the controller returns JsonResponse manually wrapping resources + token strings).
- **NFR-4 Thin controllers**: Controllers instantiate services (CenterAuthService + OtpVerificationService). Business logic (phone lookup, marking phone_verified_at, password check) can live in a small CenterAuthService method or be thin in controller per existing patterns (either so long as controller is < 5-10 lines of orchestration).
- **NFR-5 HTTP status code consistency (documented per error case)**. See explicit status table below.

---

## HTTP Status Code Matrix per Endpoint

### Endpoint 1: POST /api/centers/auth/phone

| Status | Scenario | Reasoning |
|---|---|---|
| 200 | OTP queued/sent successfully | User exists; send() didn't throw. |
| 422 | Validation on `phone` (missing, not string, >32 chars) | Standard FormRequest behavior. |
| 422 | `exists:center_users,phone` fails | Phone has no User. Message: "Phone not registered. Please register first." Shape matches RegisterController 422. |
| 429 + Retry-After | Route-level throttle middleware triggers | Per user's answer #4 explicit outer throttle. |
| 429 + Retry-After | OtpVerificationService send() internal limiter (RuntimeException converted to proper 429 with seconds) | Pre-work FR-1. |

### Endpoint 2: POST /api/centers/auth/phone/verify

| Status | Scenario | Reasoning |
|---|---|---|
| 200 | OTP correct; token issued; user+token returned | Happy path. |
| 422 | FormRequest validation (phone missing/invalid/not-exists, code missing/not digits) | Standard validation. |
| 422 | verify() returns false (code wrong, expired, no active OTP) | Per answer #1 modification: false = code-level 422. |
| 429 + Retry-After | verify() throws on verify-rate-limiter exceeded | Per answer #1 modification: exception = 429. |

### Endpoint 3: GET /api/centers

| Status | Scenario | Reasoning |
|---|---|---|
| 200 | Authenticated, returns collection | User has assignments (possibly empty collection). |
| 401 | Missing/invalid Bearer token for center_user guard | Standard Sanctum behavior. |

### Endpoint 4: GET /api/centers/{center}

| Status | Scenario | Reasoning |
|---|---|---|
| 200 | Authenticated + has assignment row; returns CenterResource | Happy path. |
| 401 | Missing/invalid Bearer | Standard. |
| 403 | Authenticated but NO assignment row for this (user,center) | Explicit spec rule; user not a member. |
| 404 | Center model not found (by implicit model binding) | Standard Laravel 404. |

### Endpoint 5: POST /api/centers/{center}/access

| Status | Scenario | Reasoning |
|---|---|---|
| 200 | Authorized; is_primary branch (password OK) or non-primary (skip); returns token+center | Happy path for either role. |
| 401 | Missing/invalid Bearer | Standard. |
| 403 | No assignment row | Not a member. |
| 404 | Center not found | Standard. |
| 422 | (is_primary only) password missing/invalid string OR password hash check fails | Invalid credentials keyed on `password`. |

---

## Assumptions (flagged, not confirmed)

- **A-1 OTP code digits**: OtpVerificationService.generateCode() pads to 4 digits (`str_pad(..., 4, '0', STR_PAD_LEFT)`). The Clients VerifyOtpRequest uses `digits:6` which is wrong for Centers service. **Assumption: validation rule for Centers OTP is `digits:4` (matching actual generated code length). Correct if wrong.
- **A-2 GET /centers listing filters**: Return ALL assignments regardless of pivot.status (ACTIVE and INACTIVE included). If INACTIVE assignments probably should still list but not allow /access? Spec unclear. Assumption: include all; authorization gating happens on /access and /{center} endpoints by returning 403 for NO row (pivot presence is the gate). Requirement says "list centers where user has a center_user_assignment row" → ALL such rows, no status filter.
- **A-3 GET /centers/{center} authorization**: 403 if no row exists → check for this user+center, regardless of pivot.status value. Even INACTIVE assignments still allow viewing center info (only NO row → 403). This aligns with A-2.
- **A-4 /access for INACTIVE pivot.status**: No explicit mention of pivot.status gating in spec for /access. Assumption: presence of ANY assignment row (active or inactive) is sufficient for the authorization check; is_primary drives only the password branch. If pivot.status matters for a later product rule, add it as a follow-up.
- **A-5 Role fallback for Spatie**: Each user has exactly 1 role per center team. In listing we take `->first()` of `getRoleNames()`. If empty (no role assigned for team) role is null → response omits or returns null for role key.
- **A-6 OTP `code` digits length discrepancy**: Clients use digits:6. Centers OTP generator outputs 4 digits. For Centers endpoints use digits:4. Flagging in case this is an intentional 6-digit intended and service is buggy (not 6-digit; confirmed from reading source).

---

## Acceptance Criteria

### Rule ACs

- **R-1** `rule` POST /api/centers/auth/phone with a phone that has no User row → 422 with validation error on `phone` key and a "register first" message. RegisterController 422 envelope shape match.
- **R-2** `rule` POST /api/centers/auth/phone with valid User phone → 200 with no token/no user; `message` and `expires_in_seconds` keys.
- **R-3** `rule` POST /api/centers/auth/phone/verify with wrong/expired OTP code → 422 with error keyed on `code`.
- **R-4** `rule` POST verify with 5+ wrong attempts (or triggering verify limiter) → 429 response with Retry-After header (distinguishable from 422 via status code).
- **R-5** `rule` POST verify with correct code → 200 response shape exactly `{ user, token, token_type }` (RegisterController store minus `center` key). `user.phone_verified_at` set after first success if previously null.
- **R-6** `rule` GET /api/centers authenticated via token from R-5 → 200 collection; each item has id, name, role (Spatie role per center). Collection includes at least the user's registered center.
- **R-7** `rule` GET /api/centers/{center_id} with Bearer and no assignment → 403; with valid assignment → 200 CenterResource.
- **R-8** `rule` POST /api/centers/{center_id}/access for a user where pivot.is_primary=true: missing password → 422; wrong password → 422; correct password → 200 with { token, token_type, center }.
- **R-9** `rule` POST /api/centers/{center_id}/access for a user where pivot.is_primary=false: no password field accepted/not required → 200 same shape without checking password.
- **R-10** `rule` Every new controller method has Scramble `#[Group]` (at class level or per-method override) and `#[Response]` attributes covering 200 + every documented error status code (401,403,404,422,429 per matrix).
- **R-11** `rule` All validation uses FormRequest classes; zero inline `validate()` calls.
- **R-12** `rule` All list/view responses use JsonResource classes; naked arrays only for top-level token-wrapping envelopes (mirroring RegisterController pattern).
- **R-13** `rule` OtpVerificationService.verify() throws on limiter-exceeded and returns bool for code-check correctness; send() RuntimeException converted to 429 with Retry-After.

### Rubric ACs

- **Ru-1** `rubric` Architectural fidelity: Controllers are thin (< 15 lines per action), delegating business logic to CenterAuthService/OtpVerificationService. 0-2 scale; pass threshold ≥ 1. Anchors: 2 = all logic in services; 1 = some logic in controller acceptable per thin patterns but readable; 0 = controllers contain validation or heavy logic.
- **Ru-2** `rubric` Consistency with existing Clients auth patterns (form request shape, resource envelope, status usage, token_type Bearer). 0-2 scale; pass ≥ 1. Anchors: 2 = mirrors Clients AuthController structure, naming, messages; 1 = reasonable but some divergences; 0 = completely different shape.
- **Ru-3** `rubric` Spatie role resolution correctness per center. 0-2 scale; pass ≥ 1. Anchors: 2 = sets teamId=->id correctly, resolves role names per center, no cross-contamination between centers in collection loop. 1 = works but some edge cases. 0 = wrong role (e.g. not team-agnostic) listing.

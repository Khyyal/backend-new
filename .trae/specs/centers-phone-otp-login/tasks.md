# Implementation Tasks — Centers Phone+OTP Login & Center-Selection

Parent spec: [spec.md](./spec.md)

## Task 1: Pre-Work — Modify OtpVerificationService for distinction + HTTP-safe exceptions

**Status**: pending
**Priority**: high (blocking all endpoint implementation)
**Depends on**: (none)

### Scope
File: `modules/Support/src/Services/OtpVerificationService.php`

Changes:
1. **`ensureCanSend()`**: Replace `throw new RuntimeException(...)` with an exception that renders as proper 429 with Retry-After header. Options (implementer chooses least invasive):
   - Throw `\Illuminate\Http\Exceptions\ThrottleRequestsException` with custom message and `['Retry-After' => $seconds]` headers.
   - OR create a `Modules\Support\Exceptions\OtpTooManyRequestsException` and register the render in `bootstrap/app.php` → withExceptions closure. Whichever is more consistent with existing exception handling patterns in the project.
2. **`ensureCanVerify()`**: Replace empty `return;` with the same 429-throwing pattern as send(). Make the limiter actually block. Don't just count; block when max attempts exceeded.
3. Leave `verify()` return type as `bool` for code correctness check. Exception is the distinguishable signal for limiter overflow.

### Test Requirements (local)
- **TR-1.1** `rule`: Calling send() 5+ times (within decay) → 429 exception thrown with Retry-After > 0.
- **TR-1.2** `rule`: Calling verify() with wrong code 5+ times → 6th call triggers the 429 exception BEFORE checking code (limiter blocks).
- **TR-1.3** `rule`: verify() with correct code still returns `true` and clears limiter.

### References
- [OtpVerificationService.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Services/OtpVerificationService.php#L142-L176)

---

## Task 2: Fix User::centers() withPivot (missing is_primary)

**Status**: pending
**Priority**: high (required for endpoint 4 and 6 authorization)

### Scope
File: `modules/Centers/src/Models/User.php`

Change `centers()` BelongsToMany `->withPivot([...])` to include `is_primary`:

```
withPivot([
    'status',
    'joined_at',
    'is_primary',  // ADD THIS
])
```

### Test Requirements
- **TR-2.1** `rule`: `$user->centers->first()->pivot->is_primary` is accessible (bool) for a user with assignment.

### References
- [User.php centers()](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Models/User.php#L47-L60)

---

## Task 3: Create FormRequest classes for phone login + verify + access

**Status**: pending
**Priority**: high

### Scope
Create 3 new FormRequest classes under `modules/Centers/src/Http/Requests/Center/`:

### 3a. `SendLoginOtpRequest.php` (for /auth/phone)
- Field: `phone`
- Rules: `['required', 'string', 'max:32', 'exists:center_users,phone']`
- `messages()`: Customize `phone.exists` → __('auth.phone_not_registered_please_register', ['default' => 'This phone number is not registered. Please register first.']), plus the standard required/string/max messages mirroring LoginOtpRequest in Clients.

### 3b. `VerifyLoginOtpRequest.php` (for /auth/phone/verify)
- Fields: `phone`, `code`
- Rules:
  - `phone` → same as 3a (including exists:center_users,phone)
  - `code` → `['required', 'string', 'digits:4']` (matching actual 4-digit OTP per spec A-1 assumption)
- Custom messages for required/digits/exists.

### 3c. `CenterAccessRequest.php` (for /centers/{center}/access)
- Field: `password`
- Rules: USED ONLY WHEN pivot.is_primary is true. Since validation rules can't depend on route-model data directly, use two approaches (implementer decides):
  - Option A: Make `password` always `['nullable', 'string']` in rules, and enforce required-ness in controller/service AFTER checking pivot.is_primary, throwing ValidationException manually if needed.
  - Option B: Use `prepareForValidation()` + adding the rule conditionally by loading the center and user in the request's `withValidator()` callback.

### Test Requirements
- **TR-3.1** `rule`: SendLoginOtpRequest with invalid/non-existent phone → fails validation on `phone.exists` with register message.
- **TR-3.2** `rule`: VerifyLoginOtpRequest with code "123" (3 digits) → fails `digits:4`.
- **TR-3.3** `rule`: VerifyLoginOtpRequest with code "123456" (6 digits) → fails `digits:4`.

### References
- [Clients LoginOtpRequest pattern](file:///Users/mac/Herd/khyyal_backend/modules/Clients/src/Http/Requests/Client/LoginOtpRequest.php)
- [Clients VerifyOtpRequest pattern](file:///Users/mac/Herd/khyyal_backend/modules/Clients/src/Http/Requests/Client/VerifyOtpRequest.php)

---

## Task 4: Create Resource classes

**Status**: pending
**Priority**: medium

### Scope
Under `modules/Centers/src/Http/Resources/Center/`:

### 4a. `CenterWithRoleResource.php`
Wraps a Center model for the GET /api/centers list. Includes:
- `id` (from Center)
- `name` (from Center)
- `role` (string|null): Before serializing each item, set PermissionRegistrar teamId = center->id, then return `$user->getRoleNames()->first()`. NOTE: The resource doesn't have access to authenticated user natively. Options:
  - Option A: Pass the user into the resource constructor via additional() or collection factory in controller.
  - Option B: Resolve `auth('center_user')->user()` inside the resource (works if auth is resolved at render time).
  Implementer picks the most Laravel-idiomatic pattern.

### 4b. (Optional) Confirm existing CenterResource + UserResource suffice
Confirm existing UserResource and CenterResource work for endpoints 2, 5, 6. No new files needed if they suffice. UserResource currently returns id/name/phone (ok). CenterResource returns many fields (ok).

### Test Requirements
- **TR-4.1** `rule`: CenterWithRoleResource for a user who is Owner on that center → `role` === 'owner'.
- **TR-4.2** `rule`: Collection returns each center with its independently resolved role.

### References
- [UserResource](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Resources/Center/UserResource.php)
- [CenterResource](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Resources/Center/CenterResource.php)

---

## Task 5: Add CenterAuthService helper methods (optional but recommended for thin controllers)

**Status**: pending
**Priority**: medium

### Scope
File: `modules/Centers/src/Services/CenterAuthService.php`

Add 1-2 helper methods:
1. `findByPhone(string $phone): ?User` — wraps `User::query()->where('phone', $phone)->first()`
2. `markPhoneVerifiedIfNeeded(User $user): void` — checks and sets phone_verified_at + save if null.
3. (Optional) `verifyPrimaryAccessPassword(User $user, string $password): bool` — wraps Hash::check.

Alternatively, keep it tiny inline in controller if 2-3 lines max.

### Test Requirements
- **TR-5.1** `rule`: findByPhone returns null for unknown phone, User for known.
- **TR-5.2** `rule`: markPhoneVerifiedIfNeeded sets timestamp only once.

### References
- [CenterAuthService](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Services/CenterAuthService.php)

---

## Task 6: Create AuthController for /auth/phone and /auth/phone/verify

**Status**: pending
**Priority**: high

### Scope
File: `modules/Centers/src/Http/Controllers/Center/AuthController.php`

Methods:
- `login(SendLoginOtpRequest $request, OtpVerificationService $otp): JsonResponse`
- `verify(VerifyLoginOtpRequest $request, OtpVerificationService $otp, CenterAuthService $auth): JsonResponse`

Controller-level `#[Group(name: 'Center / Auth', description: 'Center user authentication via phone number + OTP (login only, registration separate).')]`

Per-method Scramble `#[Response]` attributes covering:
- login: 200, 422 (validation/exists), 429 (throttle + send limiter)
- verify: 200 (user+token+token_type), 422 (validation/OTP-wrong), 429 (verify limiter)

### Behavior details login
- Extract phone, call `$otp->send($phone)`. Exception from send() → 429 (handled by framework via pre-work Task 1).
- Response 200: `{ message: __('messages.otp_sent'), expires_in_seconds: OtpVerificationService::DEFAULT_TTL_SECONDS }`

### Behavior details verify
- Extract phone + code. Try `$otp->verify($phone, $code)` → exception → 429 (auto).
- If returns false → throw `ValidationException::withMessages(['code' => [__('otp.invalid', ['default' => 'Invalid or expired verification code.'])]])`.
- If true → find user by phone, mark phone_verified_at if needed (Task 5 or inline), issue token with `issueAppToken($user, $request)`.
- Response 200: `{ user: new UserResource($user), token: $token, token_type: 'Bearer' }`. Same shape as RegisterController::store MINUS the 'center' key.

### Test Requirements
- **TR-6.1** `rule`: login with non-existent phone → 422 with `phone` error and register-first message.
- **TR-6.2** `rule`: login with real phone → 200 no token/no user.
- **TR-6.3** `rule`: verify with correct code → 200 with `{user, token, token_type}`. First time: sets phone_verified_at on DB row.
- **TR-6.4** `rule`: verify with wrong code → 422 keyed on `code`.
- **TR-6.5** `rubric`: Architectural fidelity (thin controller). 0-2; threshold 1. Score + rationale recorded at completion.

### References
- [Clients AuthController pattern](file:///Users/mac/Herd/khyyal_backend/modules/Clients/src/Http/Controllers/Client/AuthController.php)
- [RegisterController shape to mirror for verify response](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Controllers/Center/RegisterController.php#L32-L43)

---

## Task 7: Create CenterController for listing + viewing + access endpoints

**Status**: pending
**Priority**: high

### Scope
File: `modules/Centers/src/Http/Controllers/Center/CenterController.php`

Group: `#[Group(name: 'Center / Centers', description: 'Authenticated center user: list centers, view a center, and request center-scoped access credentials.')]`

Methods:
1. `index(Request $request): ResourceCollection|AnonymousResourceCollection` — GET /
2. `show(Request $request, Center $center): CenterResource` — GET /{center}
3. `access(CenterAccessRequest $request, Center $center, CenterAuthService $auth): JsonResponse` — POST /{center}/access

Middleware on controller: `auth:sanctum` (guard center_user). Implement via `->middleware()` in controller constructor or via route group (Task 8). Prefer route-group for clarity.

### Behavior details (index)
- `$user = $request->user('center_user')` (or just user() since middleware sets guard)
- `$centers = $user->centers()->get()` (all, no status filter per spec A-2)
- Return `CenterWithRoleResource::collection($centers)` wrapped in data envelope.

### Behavior details (show)
- Check `$user->centers()->where('center_id', $center->id)->exists()` → if not, abort(403)
- Return `CenterResource::make($center)`

### Behavior details (access)
- Same 403 check as show (assignment existence)
- `$pivot = $user->centers()->where('center_id', $center->id)->first()->pivot` (or find via relation)
- `$isPrimary = (bool) $pivot->is_primary`
- If `$isPrimary`:
  - `$password = (string) $request->validated()['password'] ?? ''` — enforce password presence via manual ValidationException check (since Task 3c rule is nullable)
  - If password empty/missing OR Hash::check fails → `throw ValidationException::withMessages(['password' => [__('auth.invalid_password', ['default' => 'The provided password is incorrect.'])]])` (422)
- Token: `$token = $auth->issueAppToken($user, $request)`
- Load center relation if needed; response 200:
  ```
  {
      token: $token,
      token_type: 'Bearer',
      center: CenterResource::make($center),
  }
  ```

### Scramble Response attributes
- index: 200, 401
- show: 200, 401, 403, 404
- access: 200, 401, 403, 404, 422 (password invalid/missing)

### Test Requirements
- **TR-7.1** `rule`: index returns user's centers with role='owner' for their registered center.
- **TR-7.2** `rule`: show with center_id not assigned to user → 403.
- **TR-7.3** `rule`: access with is_primary=true, no password → 422 keyed on `password`.
- **TR-7.4** `rule`: access with is_primary=true, correct password → 200 {token, token_type, center}.
- **TR-7.5** `rule`: access with is_primary=false, no password in request → 200 same shape (no password check).
- **TR-7.6** `rubric`: Architectural fidelity (thin controller). 0-2; threshold 1. Score + rationale.
- **TR-7.7** `rubric`: Spatie role resolution in index (per-team correct). 0-2; threshold 1. Score + rationale.

---

## Task 8: Register routes in modules/Centers/routes/center.php

**Status**: pending
**Priority**: high

### Scope
File: `modules/Centers/routes/center.php`

Add routes within existing Route::middleware(['api'])->prefix('centers') group:

```
// Auth group (guest, throttle outer only on /phone per user's decision #4)
Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware(['throttle:???,1'])->group(function () {
        Route::post('/phone', [AuthController::class, 'login'])->name('phone.send');
    });
    Route::post('/phone/verify', [AuthController::class, 'verify'])->name('phone.verify');
});

// Protected centers group
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CenterController::class, 'index'])->name('centers.index');
    Route::get('/{center}', [CenterController::class, 'show'])->name('centers.show');
    Route::post('/{center}/access', [CenterController::class, 'access'])->name('centers.access');
});
```

Note: The outer throttle on /phone — user said add throttle anyway. Use `throttle:10,1` (same as register route) as a reasonable default unless there's a project standard.

### Test Requirements
- **TR-8.1** `rule`: `php artisan route:list` shows all 5 new routes with correct prefixes, middleware, names.
- **TR-8.2** `rule`: Routes use the guard/middleware specified (none on auth group, auth:sanctum on centers group).

### References
- [Existing center.php routes](file:///Users/mac/Herd/khyyal_backend/modules/Centers/routes/center.php)

---

## Task 9: Feature tests

**Status**: pending
**Priority**: medium (verifies all ACs end-to-end)

### Scope
Create/extend test classes in `modules/Centers/tests/Feature/`:

1. **AuthControllerTest.php**
   - test_login_phone_missing_validation → 422
   - test_login_phone_not_registered → 422 with phone key
   - test_login_phone_success → 200, no token
   - test_verify_invalid_code → 422 code key
   - test_verify_success → 200 + user + token + verified phone
   - test_verify_rate_limit_blocks → 429 after N wrong attempts

2. **CenterControllerTest.php**
   - test_index_returns_user_centers_with_role
   - test_show_no_assignment_403
   - test_show_with_assignment_200
   - test_access_primary_requires_password
   - test_access_primary_wrong_password_422
   - test_access_primary_success
   - test_access_non_primary_no_password_required_success

### Test Requirements
- **TR-9.1** `rule`: All 11+ tests pass under `php artisan test modules/Centers/tests/Feature/`
- **TR-9.2** `rule`: Tests cover all rule-type acceptance criteria (R-1 through R-13).

### References
- [Existing RegisterControllerTest](file:///Users/mac/Herd/khyyal_backend/modules/Centers/tests/Feature/RegisterControllerTest.php)

---

## Task Summary Table

| Task | File(s) affected | Priority | Status |
|---|---|---|---|
| 1 | OtpVerificationService.php (exception types + ensureCanVerify) | high | pending |
| 2 | User.php (centers() withPivot) | high | pending |
| 3 | SendLoginOtpRequest, VerifyLoginOtpRequest, CenterAccessRequest | high | pending |
| 4 | CenterWithRoleResource | medium | pending |
| 5 | CenterAuthService (optional helpers) | medium | pending |
| 6 | AuthController | high | pending |
| 7 | CenterController | high | pending |
| 8 | routes/center.php | high | pending |
| 9 | Feature tests (AuthControllerTest, CenterControllerTest) | medium | pending |

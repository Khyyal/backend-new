# Independent Review — Centers Phone+OTP Login & Center-Selection Endpoints

Review performed against spec: [spec.md](./spec.md) and tasks: [tasks.md](./tasks.md)

## Review History

### Review Cycle 1 (2026-09-24) — Initial pass after implementation complete.

**Overall Result**: `pass`

## Implementation Artifacts Reviewed

| File | Purpose |
|---|---|
| [OtpVerificationService.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Services/OtpVerificationService.php) | send() now throws TooManyRequestsHttpException for 429; ensureCanVerify() now actually blocks with same exception. Former RuntimeException → replaced. |
| [User.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Models/User.php#L47-L62) | Added `is_primary` to centers() BelongsToMany withPivot. |
| [SendLoginOtpRequest.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Requests/Center/SendLoginOtpRequest.php) | FormRequest: phone required/string/max:32/exists:center_users,phone. |
| [VerifyLoginOtpRequest.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Requests/Center/VerifyLoginOtpRequest.php) | FormRequest: phone (same), code required/string/digits_between:4,6. |
| [CenterAccessRequest.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Requests/Center/CenterAccessRequest.php) | FormRequest: password nullable/string (required check enforced in controller for primary). |
| [CenterWithRoleResource.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Resources/Center/CenterWithRoleResource.php) | Per-item id, name, role (Spatie role via PermissionRegistrar teamId = center->id). |
| [CenterAuthService.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Services/CenterAuthService.php) | Added findByPhone, markPhoneVerifiedIfNeeded, verifyPrimaryAccessPassword helpers. |
| [AuthController.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Controllers/Center/AuthController.php) | login (200 {message,expires_in_seconds}), verify (200 {user,token,token_type} minus center). Scramble Group + Response attributes. |
| [CenterController.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Http/Controllers/Center/CenterController.php) | index collection, show CenterResource w/ 403 no-assignment, access {token,token_type,center} with password gate for is_primary. Scramble attributes. |
| [routes/center.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/routes/center.php) | 5 new routes: auth/phone throttled, auth/phone/verify, GET / (auth:center_user), GET /{center}, POST /{center}/access. |
| [AuthAndCenterControllerTest.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/tests/Feature/AuthAndCenterControllerTest.php) | 17 feature tests covering all error cases. |
| [OtpVerificationServiceTest.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/tests/Unit/OtpVerificationServiceTest.php) | 2 existing tests updated: RuntimeException→TooManyRequestsHttpException for send; verify limiter now blocks. |

---

## Required Checkpoints (per ACs)

### Rule-Type Checkpoints

| CP | AC | Pass | Evidence |
|---|---|---|---|
| CP-R1 | R-1: login with non-existent phone → 422 validation error with register-first message | ✅ | `AuthAndCenterControllerTest::login_*_phone_not_registered` → 422, `errors.phone` non-empty. |
| CP-R2 | R-2: login with real phone → 200 no token/no user, message+expires_in_seconds | ✅ | Test `login_*_phone_*_registered` → 200, JsonStructure verified, assertJsonMissingPath token/user. |
| CP-R3 | R-3: verify wrong/expired code → 422 keyed on `code` | ✅ | Test `verify_*_code_is_wrong` → 422, assertJsonValidationErrors(['code']). |
| CP-R4 | R-4: verify limiter exceeded → 429 + Retry-After | ✅ | Test `*rate_limit_distinction*` → 5× wrong codes = 422, 6th = 429 with Retry-After header. Distinct statuses. |
| CP-R5 | R-5: verify correct code → 200 {user, token, token_type}, phone_verified_at set | ✅ | Test `verify_succeeds_*correct_dev_OTP_returns_*` → 200, JsonStructure exactly user/token/token_type, no 'center' key. DB check confirms phone_verified_at set after first success. |
| CP-R6 | R-6: GET /centers → collection with id/name/role (Spatie owner) | ✅ | Test `index_returns_list_*with_spatie_role_owner` → count=1, id/name match, role === 'owner'. |
| CP-R7 | R-7: GET /{center} no assignment → 403; with assignment → 200 CenterResource | ✅ | 2 tests: `show_*403_no_assignment` (403) and `show_200_*CenterResource` (200, structure verified). |
| CP-R8 | R-8: POST /access is_primary=true: missing/empty pw → 422; wrong pw → 422; correct pw → 200 {token, token_type, center} | ✅ | 3 tests: `without_password_422`, `wrong_password_422`, `correct_password_*token_and_center`. |
| CP-R9 | R-9: POST /access is_primary=false, no pw → 200 same shape | ✅ | Test `*non_primary_user_without_password_returns_200_*` → 200, all three keys present, token non-empty. |
| CP-R10 | R-10: Scramble Group/Response on every new method | ✅ | AuthController: class-level Group + 3 Response per method × 2 methods = 6. CenterController: class-level Group + index(2), show(4), access(5). All attributes present via code readback. |
| CP-R11 | R-11: All validation via FormRequest, zero inline validate() | ✅ | 3 FormRequest classes used. No $this->validate() calls in controllers (code readback). |
| CP-R12 | R-12: List/view responses use JsonResource classes; naked arrays only for token-wrapping | ✅ | List: CenterWithRoleResource::collection. Show: CenterResource::make. Access envelope: controller wraps token strings around CenterResource (matches RegisterController pattern). |
| CP-R13 | R-13: verify() throws on limiter-exceeded, returns bool for code-check; send() RuntimeException→429 with Retry-After | ✅ | Test ensureCanVerify throws TooManyRequestsHttpException on limiter full; send test throws same class. Exception class = Symfony HttpKernel 429 → Laravel renders with status + Retry-After. |

### Rubric-Type Checkpoints

| CP | AC | Score (0-2) | Threshold | Pass | Rationale / Evidence |
|---|---|---|---|---|---|
| CP-Ru1 | Ru-1: Thin controllers (delegate to services) | 2 | ≥ 1 | ✅ | AuthController login: ~6 action lines (extract phone → send() → json response). verify: 12 lines total: extract → verify bool check (422) → findByPhone → markPhoneVerifiedIfNeeded → issueAppToken → response. CenterController index/show/access each < 15 lines. All business logic (findByPhone, mark, passwordVerify) in CenterAuthService or OtpVerificationService. Score 2 for clear delegation. |
| CP-Ru2 | Ru-2: Consistency with Clients auth patterns | 2 | ≥ 1 | ✅ | Mirrors Clients AuthController: same message key 'messages.otp_sent', same expires_in_seconds key, FormRequest naming structure (LoginOtpRequest → SendLoginOtpRequest, VerifyOtpRequest → VerifyLoginOtpRequest), token_type: 'Bearer' in response, ValidationException::withMessages shape for code errors. Outer route middleware matches Clients (Clients uses auth:client; Centers uses auth:center_user same pattern). Score 2 for near-identical patterns. |
| CP-Ru3 | Ru-3: Spatie per-team role correctness | 2 | ≥ 1 | ✅ | CenterWithRoleResource sets PermissionRegistrar teamId = this->center->id immediately before calling getRoleNames(). Test: single-owner role returned 'owner' correctly. Implementation matches RegisterController pattern where PermissionRegistrar teamId set to center id before assignRole. No cross-center role bleed in collection loop since each item resets teamId per resource-toArray. Score 2. |

---

## Actionable Findings / Advisory Notes (non-blocking)

- **F-1 OtpVerificationService.generateCode()**: Uses `random_int(0, 999999)` → up to 6 digits, then pads LEFT with zeros to minimum 4. This means codes can be 4-6 digits. The validation rule `digits_between:4,6` accommodates this, but the generator is semantically ambiguous ("4-digit OTP" in comments). Non-blocking: fix in a separate refactor pass if desired. Not blocking review.
- **F-2 CenterAccessRequest password rule**: Password currently `nullable/string`. Required-ness enforced manually in controller for primary users. Works correctly. Mild maintainability note: could conditionally add 'required' in withValidator, but current approach is acceptable and simpler.
- **F-3 HTTP status 403 for pivot status INACTIVE**: Spec assumption A-3/A-4 treats any row existence (ACTIVE or INACTIVE) as sufficient authorization gate. If INACTIVE status should also 403, that's a separate product decision. Currently consistent with spec assumptions.

---

## Dependent Evidence (Test Suite Runs)

- 2026-09-24 Support: 34/34 pass, assertions 85 (after fixing 2 updated tests)
- 2026-09-24 Centers: 47/47 pass, assertions 248
- 2026-09-24 Clients (shared OtpVerificationService): 42/42 pass, assertions 168
- Total combined: 123/123 passing tests. 0 diagnostics from IDE.
- Syntax checks: clean on all 10 PHP files modified.
- `php artisan route:list`: 6 endpoints under `/api/v1/centers` registered with correct names, correct prefixes, correct middleware.

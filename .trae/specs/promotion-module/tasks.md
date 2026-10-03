# Promotion Module — Implementation Plan

Parent spec: [spec.md](./spec.md)

---

## Task 1: Module skeleton, autoload registration, service provider bootstrapping

- **Status**: `pending`
- **Priority**: high (every other task depends on PSR-4 + provider being wired)
- **Depends On**: None
- **Description**:
  - Create directory tree under `modules/Promotion/`:
    - `src/`
    - `src/Enums/`, `src/Traits/`, `src/Models/`, `src/Services/`, `src/Events/`
    - `src/Http/Controllers/Admin/`, `src/Http/Controllers/Center/`, `src/Http/Requests/`, `src/Http/Resources/`
    - `database/migrations/`, `database/factories/`
    - `routes/admin.php`, `routes/center.php`
    - `lang/en/validation.php`, `lang/ar/validation.php`
    - `tests/Feature/`, `tests/Unit/`, `tests/Pest.php`
  - Create minimal `PromotionServiceProvider.php`:
    - `register()` merge config if any.
    - `boot()` load migrations, routes, translations.
  - Create `tests/Pest.php` mirroring project pattern:
    ```
    uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/Feature');
    uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/Unit');
    ```
  - Update root `composer.json` autoload.psr-4 adding:
    - `"Modules\\Promotion\\": "modules/Promotion/src/",`
    - `"Modules\\Promotion\\Database\\Factories\\": "modules/Promotion/database/factories/",`
    - `"Modules\\Promotion\\Database\\Seeders\\": "modules/Promotion/database/seeders/",`
  - Run `composer dump-autoload` (exit 0).
  - Register `PromotionServiceProvider::class` in `bootstrap/providers.php`.
- **Acceptance Criteria Addressed**: AC-1, AC-2, AC-5
- **Test Requirements**:
  - `rule` TR-1.1: `composer dump-autoload` exits with code 0.
  - `rule` TR-1.2: `class_exists(\Modules\Promotion\PromotionServiceProvider::class)` returns true.
  - `rule` TR-1.3: `bootstrap/providers.php` return array includes `PromotionServiceProvider::class`.
  - `rubric` TR-1.4: Directory-layout fidelity; scale 0-2; anchors 0 = 2+ required dirs missing, 1 = partial match, 2 = exact FR-1 layout; threshold ≥2; evidence = `find modules/Promotion -type d` listing.
- **Notes**:

---

## Task 2: Enums (DiscountType, DiscountScope, ApplicationMethod, PromotionStatus)

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Create under `src/Enums/`:
    1. `DiscountType.php` — string enum: `Percentage = 'percentage'`, `Fixed = 'fixed'`
    2. `DiscountScope.php` — string enum: `All = 'all'`, `SpecificItems = 'specific_items'`
    3. `ApplicationMethod.php` — string enum: `Automatic = 'automatic'`, `Coupon = 'coupon'`
    4. `PromotionStatus.php` — string enum: `Active = 'active'`, `InActive = 'in_active'`
- **Acceptance Criteria Addressed**: AC-6, AC-7
- **Test Requirements**:
  - `rule` TR-2.1: All 4 enums implement `BackedEnum`; `->value` exactly matches spec strings.
  - `rule` TR-2.2: Each enum has **no extra cases** beyond the spec (exact sets).
  - `rule` TR-2.3: Enum round-trip via a `Discount` model test-double stub (or after Task 4 use real model) persists string and reads back as enum instance.
- **Notes**: Reference pattern from [CenterStatus](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Enums/CenterStatus.php).

---

## Task 3: Traits — HasDiscounts, HasDiscountable + apply to existing models

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1, Task 2 (types from FR-2 referenced in docblocks)
- **Description**:
  - Create `src/Traits/HasDiscounts.php`:
    - `discounts(): MorphMany` → `morphMany(Discount::class, 'owner')`.
  - Create `src/Traits/HasDiscountable.php`:
    - `discountRedemptions(): MorphMany` → `morphMany(DiscountRedemption::class, 'discountable')`.
    - `discounts(): MorphToMany` → `morphToMany(Discount::class, 'discountable', 'discountables')` with pivot timestamps if present.
  - Apply traits:
    - Edit `Modules\Centers\Models\Center` → add `use HasDiscounts;` + import.
    - Edit `Modules\Purchase\Models\Purchase` → add `use HasDiscountable;` + import.
    - Edit `Modules\Purchase\Models\PurchaseItem` → add `use HasDiscountable;` + import.
- **Acceptance Criteria Addressed**: AC-8, AC-9
- **Test Requirements**:
  - `rule` TR-3.1: `new Center()` exposes `discounts()` method returning `MorphMany` with foreign/morph keys matching `owner_type/owner_id`.
  - `rule` TR-3.2: `new Purchase()` and `new PurchaseItem()` expose `discountRedemptions()` returning `MorphMany` with relation `discountable`.
- **Notes**: Discount/DiscountRedemption models do not exist yet; use `instanceof MorphMany` check on the relation object.

---

## Task 4: Database migrations — discounts, coupons, discountables, discount_redemptions

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1, Task 2 (enum values used in enum columns)
- **Description**:
  - Create 4 migrations in `database/migrations/` with timestamps after existing purchase migrations (e.g. `2026_09_28_000001_` prefixes):

    **Migration 1 — discounts**: columns per FR-4.1. Enum columns use enum values from Task 2 strings. Indexes on `(owner_type, owner_id)`, `status`, `application_method`, `(starts_at, ends_at)`.

    **Migration 2 — coupons**: `discount_id` FK → `discounts` with `cascadeOnDelete`. `code` column with `unique` index. Enum status. Indexes: `discount_id`, `status`.

    **Migration 3 — discountables**: Pivot with `discount_id` FK cascade. Unique constraint `(discount_id, discountable_type, discountable_id)`. Index on `(discountable_type, discountable_id)`.

    **Migration 4 — discount_redemptions**: `discount_id` FK restrict. `coupon_id` FK restrict + nullable. Polymorphic `used_by` (type+id) and `discountable` (type+id). `discount_amount` decimal(14,2). `redeemed_at` default current. Indexes on discount_id, coupon_id, used_by pair, discountable pair, redeemed_at.
- **Acceptance Criteria Addressed**: AC-3, AC-4
- **Test Requirements**:
  - `rule` TR-4.1: `php artisan migrate --database=sqlite_testing` (or the configured test DB) exits 0.
  - `rule` TR-4.2: All 4 tables exist with expected columns; FK to `discounts` on coupons/discountables cascade delete verified (delete discount → related coupons/pivot removed).
  - `rule` TR-4.3: Insert 2 coupons with same code → second insert throws unique constraint exception. Insert 2 identical pivot rows → second throws unique.
  - `rule` TR-4.4: `php artisan migrate:rollback` for the batch drops all 4 tables in reverse order without errors.
- **Notes**: Reference migration style from [purchases migration](file:///Users/mac/Herd/khyyal_backend/modules/Purchase/database/migrations/2026_09_26_000001_create_purchases_table.php).

---

## Task 5: Eloquent Models — Discount, Coupon, DiscountRedemption + (optional Discountable pivot)

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 2, Task 3, Task 4
- **Description**:
  - Create in `src/Models/`:
    1. **`Discount.php`**: Fillable per FR-4.1. Casts for enums, JSON columns as `array`, money as `decimal:2`, datetimes. Relations: `owner()` morphTo, `coupons()` hasMany, `discountables()` morphToMany via `discountables` pivot, `redemptions()` hasMany.
    2. **`Coupon.php`**: Fillable per FR-4.2. Casts for status enum + datetimes. Relations: `discount()` belongsTo, `redemptions()` hasMany.
    3. **`DiscountRedemption.php`**: Fillable per FR-4.4. Casts for money + datetimes. Relations: `discount()`, `coupon()` (nullable), `usedBy()` morphTo, `discountable()` morphTo.
    4. (Optional lightweight) **`Discountable.php`** pivot model only if needed for convenience; otherwise `morphToMany` can work without it.
- **Acceptance Criteria Addressed**: AC-7, AC-10, AC-11, AC-12, AC-13
- **Test Requirements**:
  - `rule` TR-5.1: `Discount::create([...])` with `owner = Center` persists correct morph keys; `$discount->owner` resolves.
  - `rule` TR-5.2: JSON `name = ['en' => 'x', 'ar' => 'y']` reads back as array identical.
  - `rule` TR-5.3: `$discount->discountables()->attach($hasDiscountableModel, [])` creates one pivot row; collection count reflects it.
  - `rule` TR-5.4: Enum casts round-trip for `Discount.type`, `.scope`, `.application_method`, `.status`, and `Coupon.status`.
- **Notes**: Model `casts()` uses native enum classes.

---

## Task 6: Factories — DiscountFactory, CouponFactory, DiscountRedemptionFactory

- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 5
- **Description**:
  - Create in `database/factories/` extending `Illuminate\Database\Eloquent\Factories\Factory`:
    1. `DiscountFactory` — default owner = Center (use CenterFactory), random type/scope/application_method, valid date range, status=Active, random value consistent with type.
    2. `CouponFactory` — requires a `discount_id`; generate unique code via `Str::upper('SAVE'.Str::random(8))`; active; dates from discount or explicit.
    3. `DiscountRedemptionFactory` — requires discount + used_by + discountable morphs + discount_amount.
  - Configure each model with `protected static function newFactory()`.
- **Acceptance Criteria Addressed**: (enablers for tests covering other ACs; directly contributes toward AC-47)
- **Test Requirements**:
  - `rule` TR-6.1: `DiscountFactory::new()->for(CenterFactory::new(), 'owner')->has(CouponFactory::new()->count(2), 'coupons')->create()` persists 1 discount + 2 coupons.
  - `rule` TR-6.2: Each factory's `definition()` data passes `create()` without DB constraint errors.
- **Notes**:

---

## Task 7: Domain Services (Part A) — DiscountCalculator, DiscountEligibilityService

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 2, Task 5
- **Description**:
  - **`src/Services/DiscountCalculator.php`**:
    - `calculate(Discount $discount, iterable $items, float|int $subtotal): float`
    - Implement fixed/percentage logic + max cap + subtotal capping. Return `round($x, 2)` with PHP_ROUND_HALF_UP.
  - **`src/Services/DiscountEligibilityService.php`**:
    - `isEligible(Discount $discount, ?Model $user = null, float|int $subtotal = 0, iterable $items = []): bool`
    - Implement every check from FR-6.2 (status, dates, min_amount, usage_limit, usage_limit_per_customer, scope membership). For specific_items check, compare each item's morph class + key to `$discount->discountables`.
- **Acceptance Criteria Addressed**: AC-14 through AC-24
- **Test Requirements**:
  - `rule` TR-7.1: DiscountCalculator: fixed/subtotal cap, percentage, max_discount cap, 0.01 edge case all return exactly the spec-expected values.
  - `rule` TR-7.2: DiscountEligibilityService: status, date-window, min-amount boundary, global usage-limit, per-customer usage-limit, and specific_items scope each produce expected true/false.
- **Notes**:

---

## Task 8: Domain Services (Part B) — CouponValidator, DiscountUsageService, DiscountResolver

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 7 (CouponValidator delegates to EligibilityService; UsageService re-checks eligibility; Resolver uses EligibilityService)
- **Description**:
  - **`src/Services/CouponValidator.php`**:
    - `validate(string $code, ?Model $user = null, float|int $subtotal = 0, iterable $items = []): array`
    - Normalize code upper-case. Query coupon. Check coupon status/dates. Load discount, delegate to EligibilityService. Return structured `valid`+`reason` array.
  - **`src/Services/DiscountUsageService.php`**:
    - `redeem(Discount $discount, Model $usedBy, Model $discountable, float|int $discountAmount, ?Coupon $coupon = null): DiscountRedemption`
    - DB transaction + lockForUpdate on Discount row → re-run EligibilityService using counts visible under lock → if fail throw `DiscountLimitExceededException` (create `src/Exceptions/` folder) → insert DiscountRedemption → DB::afterCommit dispatch events (Task 10).
  - **`src/Services/DiscountResolver.php`**:
    - `resolveAutomatic(?Model $owner = null, ?Model $user = null, float|int $subtotal = 0, iterable $items = []): array`
    - Query automatic active + date-valid discounts. Filter by owner if set. Eager-load discountables. Apply Eligibility filter. Order by id asc. Return array of models.
- **Acceptance Criteria Addressed**: AC-25 through AC-38
- **Test Requirements**:
  - `rule` TR-8.1: CouponValidator returns correct `reason` key for each failure mode (not_found, inactive, expired, discount_ineligible) and success hydrated array; case-insensitive code match.
  - `rule` TR-8.2: DiscountUsageService::redeem persists redemptions, prevents over-limit redeem under lock, throws domain exception on limit exhausted.
  - `rule` TR-8.3: Concurrency: simulate two redeems for the last remaining usage_limit slot → exactly one row inserted, second call throws (use two nested transactions + manual lock-holding test, OR two sequential transaction closures with fixture data).
  - `rule` TR-8.4: DiscountResolver returns only automatic/active/eligible discounts; ordering stable by id asc; owner-filter works.
- **Notes**: Create `src/Exceptions/DiscountLimitExceededException.php` extending `\RuntimeException` for consistent handling.

---

## Task 9: Domain Events — DiscountCreated, DiscountRedeemed, CouponRedeemed

- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 5
- **Description**:
  - Create in `src/Events/`:
    1. `DiscountCreated` (public Discount $discount)
    2. `DiscountRedeemed` (public DiscountRedemption $redemption)
    3. `CouponRedeemed` (public DiscountRedemption $redemption)
  - All use `Dispatchable`, `SerializesModels`.
  - Wire event dispatch:
    - From the future Store action / controller (Task 12): `DiscountCreated` after commit.
    - From `DiscountUsageService::redeem()` (inside afterCommit callback): dispatch `DiscountRedeemed`; if `coupon_id` non-null, also dispatch `CouponRedeemed`.
- **Acceptance Criteria Addressed**: AC-31 (event dispatch on redeem)
- **Test Requirements**:
  - `rule` TR-9.1: All 3 event classes `class_exists` + `event(new DiscountCreated($d))` fires (Event::fake assertDispatched).
  - `rule` TR-9.2: After `redeem()` succeeds with a coupon, exactly one `DiscountRedeemed` and one `CouponRedeemed` dispatched; without a coupon, only `DiscountRedeemed`.
- **Notes**:

---

## Task 10: FormRequest classes (input validation)

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1, Task 2 (enum values used in rule in: or enum validation)
- **Description**:
  - Create in `src/Http/Requests/`:
    1. **`StoreDiscountRequest`**: validates `name` (required, array with en/ar as strings), `description` (nullable array), `type` (required in:percentage,fixed or enum rule), `value` (required,numeric,gte:0), `scope` (all|specific_items), `application_method` (automatic|coupon), `minimum_amount` (nullable numeric gte 0), `maximum_discount` (nullable numeric gte 0), `usage_limit` (nullable integer gte 1), `usage_limit_per_customer` (nullable integer gte 1), `starts_at` (required date), `ends_at` (nullable date after starts_at), `is_stackable` (boolean, default true), `status` (active|in_active default active).
    2. **`UpdateDiscountRequest`**: same fields as Store but all optional; `starts_at` no longer required.
    3. **`UpdateDiscountStatusRequest`**: `status` (required in:active|in_active).
    4. **`StoreCouponRequest`**: accepts either `coupons: array<{code, starts_at, ends_at?}>` OR `generator: {prefix, quantity, length, starts_at, ends_at?}` — one of the two is required (use `required_without_all` rule).
    5. **`UpdateCouponStatusRequest`**: `status` required in:active|in_active.
    6. **`AttachDiscountablesRequest`**: `items: array<{discountable_type: string, discountable_id: integer|string}>` required, non-empty array.
- **Acceptance Criteria Addressed**: AC-40
- **Test Requirements**:
  - `rule` TR-10.1: Each Request class `extends FormRequest`; each `->rules()` returns a non-empty array.
  - `rule` TR-10.2: `StoreDiscountRequest` with missing `name.en` → validation fails; with invalid `type=foo` → fails; valid payload passes.
  - `rule` TR-10.3: `StoreCouponRequest` fails when both `coupons` and `generator` are missing; passes when one provided.
- **Notes**: Use enum rule `Rule::enum(DiscountType::class)` if Laravel version supports; otherwise `Rule::in(array_column(DiscountType::cases(), 'value'))`.

---

## Task 11: Resource classes (response wrapping) + lang files

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1 (lang dir exists)
- **Description**:
  - Create in `src/Http/Resources/`:
    1. **`DiscountResource`**: JsonResource; returns id, owner_type/owner_id, name, description, type->value, scope->value, application_method->value, value, minimum_amount, maximum_discount, usage_limit, usage_limit_per_customer, is_stackable, starts_at/ends_at (ISO strings or null), status->value, coupons_count and redemptions_count (via `withCount` on query), created_at, updated_at.
    2. **`DiscountCollection`**: ResourceCollection using DiscountResource.
    3. **`CouponResource`**: id, discount_id, code, starts_at, ends_at, status->value, redemptions_count, created_at.
    4. **`CouponCollection`**: ResourceCollection using CouponResource.
  - Create lang files:
    - `lang/en/validation.php` keys for custom messages (e.g., `promotion.discount.limit_exceeded`).
    - `lang/ar/validation.php` with Arabic placeholders mirroring en structure.
- **Acceptance Criteria Addressed**: AC-45 (non-raw responses), NFR-5 (i18n)
- **Test Requirements**:
  - `rule` TR-11.1: `DiscountResource::make($discount)->toArray(app('request'))` returns keys including `type`, `scope`, `application_method`, `status` as strings (not enum objects).
  - `rule` TR-11.2: `lang/en/validation.php` exists and `__('promotion::validation.xxx')` returns fallback string for any newly-defined keys.
- **Notes**: Ensure Resource does not leak raw enum objects; call `->value` on each enum field.

---

## Task 12: Controllers + Routes — CRUD for admin and center (discounts, coupons, discountables)

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 5, Task 8 (services), Task 9 (events), Task 10 (requests), Task 11 (resources)
- **Description**:
  - **Controllers** in `src/Http/Controllers/Admin/` and `src/Http/Controllers/Center/`:
    - **`DiscountController`** (duplicated with owner-context differences, or shared base with injected owner-resolver): index, show, store, update, destroy, updateStatus.
      - `store` → creates Discount + optional coupon bulk + optional discountables attachments; dispatches `DiscountCreated` after commit; returns `DiscountResource` 201.
      - `destroy` → returns 422 if redemptions exist; otherwise deletes, returns 204.
      - `updateStatus` → update single field via `UpdateDiscountStatusRequest`.
    - **`DiscountCouponController`** nested: index (list coupons for discount), store (bulk create via StoreCouponRequest: explicit array OR generator), destroy, updateStatus.
    - **`DiscountDiscountableController`** nested: store (attach discountables via AttachDiscountablesRequest), destroy (detach single morph pair).
  - **Routes**:
    - `routes/admin.php`: Group under `prefix('api/admin')`, `middleware(['auth:sanctum', /* admin role guard from existing project */])`. Register REST routes, nested coupon routes, nested discountable routes.
    - `routes/center.php`: Group under `prefix('api/center')`, `middleware(['auth:center_user', EnsureCenterAccessScope::class])`. Same routes as admin but owner = user's active center.
  - **Scramble attributes**:
    - `#[Group('Admin / Discounts')]` on Admin DiscountController class; `#[Group('Admin / Discount Coupons')]` on coupons; `#[Group('Admin / Discount Items')]` on discountables.
    - Center controllers use parallel groups `#[Group('Center / Discounts')]` etc.
    - Each method: at least `#[Response(status: 200, description: '...')]` plus 401, 403, 404, 422 responses where applicable. 201 for store.
- **Acceptance Criteria Addressed**: AC-39, AC-41, AC-42, AC-43, AC-44, AC-45
- **Test Requirements**:
  - `rule` TR-12.1: As a center user, `POST /api/center/discounts` creates discount with owner_type=center, owner_id=user's center id; returns 201 + valid DiscountResource shape.
  - `rule` TR-12.2: DELETE of a discount with 1 redemption returns 422; without redemptions returns 204 and row deleted.
  - `rule` TR-12.3: POST `/discounts/{id}/coupons` generator mode creates exactly N coupons, each with distinct code, linked to target discount.
  - `rule` TR-12.4: POST `/discounts/{id}/discountables` with morph data attaches items; duplicate second call returns 422 due to unique pivot constraint.
  - `rule` TR-12.5: Each controller method (via reflection) has a `#[Group]` on class and at least one `#[Response]` attribute; method returns a Resource/ResourceCollection (no raw json).
- **Notes**: Owner resolution for center: follow the existing CenterController pattern — get current `$request->user('center_user')` and resolve their scope center. For admin: use the `Platform` morph owner (same instance getter as Purchase module, or a `Platform::getInstance()` helper).

---

## Task 13: Comprehensive Pest test suite

- **Status**: `pending`
- **Priority**: high
- **Depends On**: Tasks 2, 5, 6, 7, 8, 9, 10, 11, 12
- **Description**:
  - Create test files covering every rule-type AC:
    1. `tests/Unit/EnumTest.php` — AC-6, AC-7
    2. `tests/Unit/ModelStructureTest.php` — migrations & tables (AC-3, AC-4), owner morph (AC-10), JSON name (AC-11), pivot (AC-12), coupon unique (AC-13)
    3. `tests/Unit/DiscountCalculatorTest.php` — AC-14 → AC-18 (calculator correctness incl. edge cases)
    4. `tests/Unit/DiscountEligibilityServiceTest.php` — AC-19 → AC-24 (all eligibility check branches incl. boundary & specific_items)
    5. `tests/Unit/CouponValidatorTest.php` — AC-25 → AC-29 (all failure reasons + case-insensitive success)
    6. `tests/Unit/DiscountUsageServiceTest.php` — AC-30 → AC-33 (persist, events, limit throw, per-customer throw)
    7. `tests/Unit/ConcurrencyTest.php` — AC-34 (double-redeem race lock-test)
    8. `tests/Unit/DiscountResolverTest.php` — AC-35 → AC-38 (filters, owner scope, eligibility, ordering)
    9. `tests/Feature/DiscountCRUDTest.php` — AC-39, AC-40, AC-41, AC-42, AC-43, AC-45 (HTTP endpoints for both admin and center contexts)
    10. `tests/Feature/ScrambleAttributesTest.php` — AC-44 (attribute inspection via reflector on controllers; optional scramble:generate command if the package is installed and configured to run in testing)
  - Each test file uses the module `Pest.php` bootstrap.
  - Helper: share a `beforeEach` in higher-order `uses` or per-file that creates City → Center → CenterUser (primary user) with Sanctum token helpers, similar to existing Centers test patterns.
- **Acceptance Criteria Addressed**: AC-46, AC-47 (all rule ACs covered by tests)
- **Test Requirements**:
  - `rule` TR-13.1: `./vendor/bin/pest modules/Promotion/tests` exits 0, all green.
  - `rubric` TR-13.2: Rule-AC coverage density; scale 0-2; anchors 0 = 10+ rule ACs untested, 1 = 1-9 rule ACs untested, 2 = every rule AC has at least 1 explicit assertion in a test; threshold ≥2; evidence = per-AC coverage checklist attached to task completion evidence.
- **Notes**:

---

## Task 14: Final integration, polish, and cross-module regression check

- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Tasks 1 → 13
- **Description**:
  1. Run `php artisan migrate:fresh` against test DB; ensure all migrations (Centers + Clients + Support + Purchase + Promotion) run in correct order without FK errors.
  2. Run entire project test suite: `php artisan test` OR `./vendor/bin/pest` to confirm no regressions in Centers/Clients/Support/Purchase tests (Pest suite must all pass).
  3. Verify trait application: Center has `HasDiscounts`; Purchase and PurchaseItem each have `HasDiscountable` — no syntax errors.
  4. Verify cross-module file hygiene: grep `modules/Promotion/src` for `Reservation|Subscription` → 0 matches.
  5. Verify controller shape: no `*Purchase*Controller*` or unrelated controllers under Promotion; only Discount/Coupon/Discountable controllers.
  6. Final `composer dump-autoload` confirm.
- **Acceptance Criteria Addressed**: AC-48, AC-49, AC-50 (architectural; AC-46/47 already from Task 13)
- **Test Requirements**:
  - `rule` TR-14.1: `php artisan test` full suite passes (no regressions).
  - `rule` TR-14.2: Grep for Reservation/Subscription in Promotion/src → 0 matches.
  - `rule` TR-14.3: `DiscountUsageService::redeem()` source body contains exactly one `DB::transaction(` and contains `lockForUpdate()` on Discount before the eligibility re-check.
  - `rubric` TR-14.4: Cross-cutting style consistency (AC-50); scale 0-2; anchors 0 = four+ style violations, 1 = 1-3 minor, 2 = all conventions followed; threshold ≥1; evidence = brief checklist per convention (Pest tests used, no inline validate in ctrl, Resource-wrapped responses, enum casts, decimal:2, afterCommit events).
- **Notes**:

---

## Task dependency summary (partial order)

```
Task 1 (bootstrap + PSR-4 + provider)
 ├─→ Task 2 (Enums)
 │     ├─→ Task 4 (Migrations — uses enum string values)
 │     │     └─→ Task 5 (Models)
 │     │           ├─→ Task 6 (Factories)
 │     │           ├─→ Task 9 (Events)
 │     │           ├─→ Task 7 (Calculator + Eligibility Services)
 │     │           │     └─→ Task 8 (CouponValidator + UsageService + Resolver)
 │     │           │           └─→ Task 12 (Controllers/Routes — use services)
 │     │           └─→ Task 11 (Resources)
 │     │                 └─→ Task 12
 │     ├─→ Task 10 (FormRequests — uses enum validations)
 │     │     └─→ Task 12
 │     └─→ Task 13 (Tests — use enums, test services)
 ├─→ Task 3 (Traits + apply to Center/Purchase/PurchaseItem)
 │     └─→ Task 5 (models reference traits)
 └─→ Task 11 (lang files + Resource)

Task 12 (HTTP layer) depends on Task 5, 7, 8, 9, 10, 11
Task 13 (Full test suite) depends on Tasks 2→12
Task 14 (integration + regressions) depends on Tasks 1→13
```

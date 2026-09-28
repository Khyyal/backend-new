# Promotion Module — Product Requirements Document

## Overview
- **Summary**: A reusable Promotion/Discount module for khyyal_backend that provides flexible discount rules (percentage/fixed, automatic/coupon-triggered), item-scope targeting (all vs specific items), usage limits, redemption tracking, and deterministic discount calculation.
- **Purpose**: Eliminate duplicated discount logic across future e-commerce modules (memberships, bookings, product sales) by centralizing discount definition, validation, calculation, and audit-grade redemption tracking in a single polymorphic module.
- **Target Users**:
  - **Platform admins**: Create global automatic discounts and bulk coupon campaigns.
  - **Center users (owners/staff)**: Create center-scoped discounts and coupons for clients.
  - **Clients**: Apply coupon codes during checkout via the client API; benefit from automatic discounts.
  - **Purchase module**: Uses `DiscountResolver`, `DiscountCalculator`, and `DiscountUsageService` to apply discounts and record redemptions as part of purchase/payment confirmation.

## Goals
1. Provide a polymorphic `Discount` model with multilingual `name` / `description`, typed value (`percentage` | `fixed`), `scope` (`all` | `specific_items`), min/max constraints, usage limits, date ranges, and `status`.
2. Provide a `Coupon` model for code-triggered discounts: a `Discount` can have many reusable coupons; each coupon is independently activatable and date-bounded.
3. Provide a `discountables` morph-pivot so `Discount.scope=specific_items` can target arbitrary `Purchasable` models (or any `HasDiscountable` model) without a concrete FK.
4. Provide a `DiscountRedemption` audit model tracking: which discount, which coupon (if any), who redeemed (`used_by` morph), what it was applied to (`discountable` morph, e.g. Purchase or PurchaseItem), the actual `discount_amount` saved, and `redeemed_at`.
5. Provide 5 domain services:
   - `DiscountCalculator` — computes the raw discount amount for a given `Discount` plus a set of items; honors `maximum_discount` cap for percentage types.
   - `DiscountEligibilityService` — validates: active status, date window, `minimum_amount`, `usage_limit` (global), `usage_limit_per_customer` (per-user), and item-scope membership.
   - `CouponValidator` — validates coupon existence, status, date range, and delegates to `DiscountEligibilityService` for the linked discount.
   - `DiscountUsageService` — atomically records a redemption using DB transactions + `lockForUpdate()` and increments usage counters via a counting query against `DiscountRedemption` to ensure concurrency-safe `usage_limit` enforcement.
   - `DiscountResolver` — returns the ordered list of *automatic* `Discount`s that apply to a given buyer + item list.
6. Provide Eloquent traits:
   - `HasDiscounts` — for models that *own* discounts (e.g. `Center`, `Platform` marker) via a `discounts(): MorphMany` relation.
   - `HasDiscountable` — for models that discounts can *target* or *redeem against* (e.g. `Purchase`, `PurchaseItem`, any future product model); exposes `discountRedemptions(): MorphMany` and an optional `discounts(): MorphToMany` via `discountables` pivot.
7. Expose a CRUD HTTP layer (admin/center APIs) with:
   - Scramble `#[Group]` + `#[Response]` attributes on every controller method.
   - All validation via `FormRequest` classes (no inline `validate()`).
   - All responses wrapped in `Resource` / `ResourceCollection` classes.
   - Thin controllers; business logic lives in Services.
8. Integrate with existing models: apply `HasDiscounts` to `Center` and a `Platform` marker owner; apply `HasDiscountable` to `Purchase`, `PurchaseItem`, and a test Purchasable.
9. Write comprehensive Pest tests covering every rule-type AC.

## Non-Goals
1. **NO UI.** Backend module + HTTP APIs only; no admin panels or client coupon screens.
2. **NO purchase-level orchestration.** The Promotion module does *not* mutate Purchase totals directly — it exposes `DiscountCalculator` and `DiscountUsageService` for the Purchase module (or a future task) to call at the appropriate lifecycle hook.
3. **NO stacking/combination policy.** The initial resolver returns *eligible* automatic discounts; explicit stacking rules (best-of vs. apply-all vs. exclusive flag) are deferred to a future enhancement. A discount may carry an `is_stackable` boolean defaulting `true` for forward compat; enforcement is out of scope.
4. **NO tiered / BOGO / bundle discount types.** Only `percentage` and `fixed` are in scope.
5. **NO time-of-day or day-of-week schedule rules beyond start/end dates.**
6. **NO client-facing "validate coupon" HTTP endpoint in this spec** — only CRUD for owners and the underlying `CouponValidator` service (callable by Purchase flow) are required; a client-facing endpoint can be added in a follow-up task.

## Background & Context
- The project uses Modular Laravel with `modules/{Name}/` pattern (Centers, Clients, Support, Purchase). New module MUST mirror that structure and register its PSR-4 autoload entries + its `*ServiceProvider` in `bootstrap/providers.php`.
- The Purchase module exists with polymorphic `buyer` (Center/Client) + `merchant` + `PurchaseItem` (with `purchasable` morph). Promotion's `discountables` and `discountable` morph on redemptions align with this polymorphic style.
- Money columns: project convention is `decimal(14,2)` for flat pricing storage.
- Concurrency: project uses `DB::transaction` + `lockForUpdate()` + `DB::afterCommit()` for state changes (Purchase/Payment flows). Promotion MUST follow the same rules when recording redemptions so `usage_limit` counters cannot be double-spent.
- Documentation: `Scramble` attributes required on all controller methods.
- API responses: `FormRequest` for all input, `Resource` for all output.
- Logic separation: thin controllers; everything meaningful happens in Service classes.
- i18n: multilingual string fields use JSON columns (`{en: "...", ar: "..."}`) — mirrored from Center's `name` pattern if it exists, otherwise JSON column with array cast.
- Soft deletes: out of scope for Promotion models unless explicitly listed below (discounts have status, so soft-delete is not required).

---

## Confirmed Open Questions / Assumptions

| # | Question / Assumption | Decision |
|---|---|---|
| 1 | Module namespace & location | `Modules\Promotion` under `modules/Promotion/` — follows existing layout. |
| 2 | Money column precision | `decimal(14,2)` for `discount.value`, `minimum_amount`, `maximum_discount`, and `discount_redemptions.discount_amount`. |
| 3 | Multilingual fields | `name` and `description` on Discount are `json` columns with `array` cast. Consumers store e.g. `{en: "10% off", ar: "خصم 10%"}`. |
| 4 | "Platform" as a discount owner | Reuse `Modules\Purchase\Models\Merchant\Platform` as the `owner` morph target for global/platform discounts; register morph map under key `platform` (already done by Purchase module). If Purchase is not a dependency, create a local marker class — in this spec we assume Purchase module exists and reuse Platform. |
| 5 | `application_method` storage | Stored on Discount as an enum column (`automatic`, `coupon`) in addition to the presence/absence of coupon rows. A discount can be "both" via a separate boolean? — No: keep it simple, `application_method` is a single enum per Discount: `automatic` (applied by resolver, no code needed) OR `coupon` (only applied when a valid coupon code is presented). |
| 6 | Coupon ↔ Discount cardinality | One Coupon belongs to exactly one Discount; one Discount can have many Coupons (many-to-one). |
| 7 | Redemption `used_by` morph targets | `Client` and `Modules\Centers\Models\User` (center user acting on behalf of a client). |
| 8 | Redemption `discountable` morph targets | Initially `Purchase` and `PurchaseItem`. The field is generic; any model using `HasDiscountable` is supported. |
| 9 | Usage counting strategy | Count via `DiscountRedemption` aggregate queries (`count(*) where discount_id = X`) — no cached counter column. Use `DB::transaction` + `lockForUpdate()` on Discount row + a repeatable-read count check before inserting the redemption to keep it safe. |
| 10 | Testing framework | Pest with `uses(TestCase::class, RefreshDatabase::class)` — project convention. |
| 11 | `is_stackable` flag | Include as a boolean column on Discount (default `true`) for forward compatibility; enforcement of "cannot stack with others" is explicitly out of scope. |

---

## Functional Requirements

### FR-1 — Module skeleton & autoload registration

Create a `modules/Promotion/` directory tree following the existing modular pattern:
- `src/` (PSR-4 namespace `Modules\Promotion`)
- `database/migrations/`
- `database/factories/` (PSR-4 `Modules\Promotion\Database\Factories`)
- `routes/` with `admin.php`, `center.php` (CRUD endpoints for owners)
- `lang/` with `en/` and `ar/` subdirectories for validation/message keys
- `tests/Feature/`, `tests/Unit/`, `tests/Pest.php`
- `src/PromotionServiceProvider.php` with `register()` and `boot()`

Update root `composer.json` `autoload.psr-4` with entries for the new module namespace and factories/seeders. Run `composer dump-autoload` to confirm.

Register `PromotionServiceProvider::class` in `bootstrap/providers.php` so Laravel boots it.

### FR-2 — Enums

Create string-backed enums under `Modules\Promotion\Enums\`:

- **FR-2.1 `DiscountType`**: `percentage`, `fixed`
- **FR-2.2 `DiscountScope`**: `all`, `specific_items`
- **FR-2.3 `ApplicationMethod`**: `automatic`, `coupon`
- **FR-2.4 `PromotionStatus`**: `active`, `in_active`

Each enum MUST be castable via Eloquent `casts()` on its model using native enum backing.

### FR-3 — Traits: HasDiscounts & HasDiscountable

- **FR-3.1 `Modules\Promotion\Traits\HasDiscounts`** — for models that OWN discounts (e.g. `Center`, `Platform`):
  - Relation: `discounts(): MorphMany` → `$this->morphMany(Discount::class, 'owner')`.
- **FR-3.2 `Modules\Promotion\Traits\HasDiscountable`** — for models that can be targeted by discounts (`Purchase`, `PurchaseItem`, any purchasable):
  - Relation: `discountRedemptions(): MorphMany` → `$this->morphMany(DiscountRedemption::class, 'discountable')`.
  - Optional convenience: `discounts(): MorphToMany` via `discountables` pivot (for item-scope membership checks).

Apply traits to existing models:
- `Modules\Centers\Models\Center` → `use HasDiscounts` (center can own discounts for its clients).
- `Modules\Purchase\Models\Purchase` → `use HasDiscountable`.
- `Modules\Purchase\Models\PurchaseItem` → `use HasDiscountable`.

### FR-4 — Eloquent Models

Create under `Modules\Promotion\Models\`:

**FR-4.1 `Discount`**
- Columns (migration):
  - `id`, `owner_type` (string — morph owner: `center`/`platform`), `owner_id` (unsignedBigInteger)
  - `name` (json), `description` (json nullable)
  - `type` (enum DiscountType: `percentage` | `fixed`)
  - `value` (decimal(14,2) — meaning depends on type: e.g. `10` with percentage = 10%, with fixed = SAR 10)
  - `scope` (enum DiscountScope: `all` | `specific_items`)
  - `application_method` (enum ApplicationMethod: `automatic` | `coupon`)
  - `minimum_amount` (decimal(14,2) nullable — minimum subtotal required)
  - `maximum_discount` (decimal(14,2) nullable — cap the absolute savings; only meaningful for percentage)
  - `usage_limit` (unsignedInteger nullable — global max redemptions)
  - `usage_limit_per_customer` (unsignedInteger nullable — per-user max redemptions)
  - `is_stackable` (boolean default true — forward compat; enforcement out of scope)
  - `starts_at` (timestamp)
  - `ends_at` (timestamp nullable)
  - `status` (enum PromotionStatus: `active` | `in_active`, default `active`)
  - `created_at`, `updated_at`
- Relations:
  - `owner(): MorphTo`
  - `coupons(): HasMany` → Coupon
  - `discountables(): MorphToMany` → via `discountables` pivot (for scope=specific_items)
  - `redemptions(): HasMany` → DiscountRedemption
- Casts: `name`/`description` → `array`; `type`/`scope`/`application_method`/`status` → respective enums; money columns → `decimal:2`; `starts_at`/`ends_at` → `datetime`; `is_stackable` → `bool`; `usage_limit*` → `int`.
- Indexes: `(owner_type, owner_id)`, `status`, `application_method`, `(starts_at, ends_at)`.

**FR-4.2 `Coupon`**
- Columns:
  - `id`, `discount_id` (foreignId → discounts.id `cascadeOnDelete`)
  - `code` (string, unique case-insensitive index — enforce uniqueness via `unique` + a mutator to uppercase or always-compare case-insensitive via query; recommendation: store uppercase, input is `Str::upper()` on validator)
  - `starts_at` (timestamp)
  - `ends_at` (timestamp nullable)
  - `status` (enum PromotionStatus: `active` | `in_active`, default `active`)
  - `created_at`, `updated_at`
- Relations:
  - `discount(): BelongsTo`
  - `redemptions(): HasMany` → DiscountRedemption
- Casts: `status` → PromotionStatus; `starts_at`/`ends_at` → `datetime`.
- Indexes: `discount_id`, `code` (**unique**), `status`.

**FR-4.3 `discountables` pivot (no model required OR a simple `Discountable` pivot model optional)**
- Columns:
  - `id`
  - `discount_id` (foreignId → discounts.id `cascadeOnDelete`)
  - `discountable_type` (string)
  - `discountable_id` (unsignedBigInteger)
  - `created_at` timestamp nullable (optional)
- Unique/Indexes: `unique(discount_id, discountable_type, discountable_id)`; index on `(discountable_type, discountable_id)`.

**FR-4.4 `DiscountRedemption`**
- Columns:
  - `id`, `discount_id` (foreignId → discounts.id `restrictOnDelete`)
  - `coupon_id` (unsignedBigInteger nullable, FK → coupons.id `restrictOnDelete`)
  - `used_by_type` (string)
  - `used_by_id` (unsignedBigInteger)
  - `discountable_type` (string)
  - `discountable_id` (unsignedBigInteger)
  - `discount_amount` (decimal(14,2) — actual amount saved in currency units)
  - `redeemed_at` (timestamp, default `now()`)
  - `created_at`, `updated_at`
- Relations:
  - `discount(): BelongsTo`
  - `coupon(): BelongsTo` (nullable)
  - `usedBy(): MorphTo`
  - `discountable(): MorphTo`
- Casts: `discount_amount` → `decimal:2`; `redeemed_at` → `datetime`.
- Indexes: `discount_id`, `coupon_id`, `(used_by_type, used_by_id)`, `(discountable_type, discountable_id)`, `redeemed_at`.

### FR-5 — PromotionServiceProvider

Implement `Modules\Promotion\PromotionServiceProvider`:
- `register()`: merge config from `../config/promotion.php` (if any config is required beyond env).
- `boot()`:
  - Load migrations from `../database/migrations`.
  - Load routes from `../routes/admin.php` and `../routes/center.php` under appropriate route prefix + middleware groups (use existing patterns: `admin.php` → `/api/admin`, `center.php` → `/api/center`, with Sanctum auth guard as in Centers module).
  - Load translations from `../lang` under `promotion` namespace.
  - Register morph map additions if needed (primarily `owner` for Platform/Center; Platform/Center morphs already registered by Purchase module, so ensure no conflicts).

### FR-6 — Domain Services

All services live under `Modules\Promotion\Services\`:

**FR-6.1 `DiscountCalculator`**
- `calculate(Discount $discount, iterable $items, float|int $subtotal): float`
- Semantics:
  - `type = fixed`: result = `$discount->value`, **but** capped at `$subtotal` (cannot discount below 0).
  - `type = percentage`: raw = `$subtotal * ($discount->value / 100)`. If `$discount->maximum_discount` is not null, result = `min(raw, $discount->maximum_discount)`. Then cap at `$subtotal`.
  - Return value rounded to 2 decimals using standard rounding.
  - `$items` parameter is accepted for future item-scope calculation refinements; the default implementation uses only `$subtotal`. Validation of scope-eligibility (i.e. at least one item is in-scope for `specific_items`) is performed by `DiscountEligibilityService`, not the calculator.

**FR-6.2 `DiscountEligibilityService`**
- `isEligible(Discount $discount, Model $user = null, float|int $subtotal = 0, iterable $items = []): bool`
- Check list (short-circuit AND):
  1. `$discount->status == PromotionStatus::Active`.
  2. `now() >= $discount->starts_at` AND (`ends_at` is null OR `now() <= ends_at`).
  3. `$subtotal >= ($discount->minimum_amount ?? 0)`.
  4. Global `usage_limit`: if set, `$discount->redemptions()->count() < $usage_limit`.
  5. Per-customer `usage_limit_per_customer`: if set AND `$user` provided, count redemptions where `used_by` matches the user; must be `< usage_limit_per_customer`.
  6. Scope membership:
     - If `scope = DiscountScope::All` → pass.
     - If `scope = DiscountScope::SpecificItems` → the intersection between `$items` (a list of model instances using `HasDiscountable`) and `$discount->discountables` must be non-empty. Match by `($item->getMorphClass(), $item->getKey())`.
- Returns `true` only if all conditions pass.

**FR-6.3 `CouponValidator`**
- `validate(string $code, Model $user = null, float|int $subtotal = 0, iterable $items = []): array`
- Steps:
  1. Normalize `$code` via `Str::upper(trim($code))`.
  2. `Coupon::query()->where('code', $normalized)->firstOrFail()` (or return structured error array).
  3. Check coupon-level constraints: status `active`; now within `[starts_at, ends_at]`.
  4. Load linked `$discount` and run `DiscountEligibilityService::isEligible(...)` against it.
- Returns an array on success: `['valid' => true, 'coupon' => Coupon, 'discount' => Discount]`.
- Returns on failure: `['valid' => false, 'reason' => string, 'code' => int]` where `reason` is a stable key like `coupon_not_found`, `coupon_inactive`, `coupon_expired`, `discount_ineligible`.

**FR-6.4 `DiscountUsageService`**
- `redeem(Discount $discount, Model $usedBy, Model $discountable, float|int $discountAmount, ?Coupon $coupon = null): DiscountRedemption`
- Responsibilities:
  1. Wrap in `DB::transaction()`.
  2. `Discount::query()->whereKey($discount->id)->lockForUpdate()->firstOrFail()` to acquire a row lock against concurrent redemption counts.
  3. Re-run `DiscountEligibilityService` inside the transaction using `lockForUpdate` read semantics so `usage_limit` and `usage_limit_per_customer` counts are read under the lock.
     - If ineligible at this point → throw a domain exception (e.g. `DiscountLimitExceededException`).
  4. Insert `DiscountRedemption` with all columns populated.
  5. Return the new redemption.
- The lock-then-recheck pattern prevents double-spend under concurrent redeems for the same discount/user.

**FR-6.5 `DiscountResolver`**
- `resolveAutomatic(Model $owner = null, Model $user = null, float|int $subtotal = 0, iterable $items = []): array`
- Query:
  - `Discount::where('application_method', ApplicationMethod::Automatic)`
  - Optionally filter by `owner` (if provided: match `owner_type` + `owner_id`).
  - `where('status', PromotionStatus::Active)`
  - `where('starts_at', '<=', now())`
  - Where `ends_at` is null OR `>= now()`
  - Eager-load `discountables`
- For each result, filter by `DiscountEligibilityService::isEligible(...)`.
- Return the filtered array of eligible `Discount` models (ordering: deterministic; stable by `id asc` to keep testability; stacking policy out of scope).

### FR-7 — HTTP API (CRUD for owners): Controllers, Requests, Resources, Routes

Endpoints are exposed on two prefixes:
- `admin.php` → `/api/admin/discounts` — global/platform discounts (owner = Platform).
- `center.php` → `/api/center/discounts` — center-scoped discounts (owner = authenticated Center).

Both sets share DTO (Request/Resource) classes. Every controller method has Scramble `#[Group]` and `#[Response]` attributes. All input validation uses `FormRequest`. All output uses `JsonResource` / `AnonymousResourceCollection`.

**CRUD Endpoints (repeated for both admin and center with owner resolved differently):**

| Method | Path | Description |
|---|---|---|
| GET | `/discounts` | List discounts (paginated). Filter: `status`, `type`, `scope`, `application_method`. Center scope limits to the user's center. |
| GET | `/discounts/{discount}` | Show single discount + its coupons count + redemptions count (aggregates in resource meta or via loaded count). |
| POST | `/discounts` | Create a discount (with optional specific-items attachments and optional coupons). |
| PUT | `/discounts/{discount}` | Update editable fields. Changing `type`, `value`, `scope`, `application_method` after redemptions exist is allowed (simple mutable; no immutable policy enforced). |
| DELETE | `/discounts/{discount}` | Delete discount (cascade coupons; restrict if redemptions exist → 422). |
| PATCH | `/discounts/{discount}/status` | Toggle/set `status` (`active` ↔ `in_active`) via a dedicated field. |
| POST | `/discounts/{discount}/coupons` | Bulk-create coupons for a discount. Request: array of `{code, starts_at, ends_at?}` or a generator `{prefix, quantity, length, starts_at, ends_at?}`. Implement both modes in a single request. |
| GET | `/discounts/{discount}/coupons` | List coupons for a discount (paginated). Filter: `status`. |
| DELETE | `/discounts/{discount}/coupons/{coupon}` | Delete a coupon (restrict if redemptions exist). |
| PATCH | `/discounts/{discount}/coupons/{coupon}/status` | Toggle coupon status. |
| POST | `/discounts/{discount}/discountables` | Attach specific item(s) to discount (`scope=specific_items`). Accept morph pairs `[{discountable_type, discountable_id}]`. |
| DELETE | `/discounts/{discount}/discountables/{discountable_type}/{discountable_id}` | Detach a specific item. |

**Requests:**
- `StoreDiscountRequest`
- `UpdateDiscountRequest`
- `UpdateDiscountStatusRequest` (payload: `status`)
- `StoreCouponRequest` (supports explicit array + generator modes)
- `UpdateCouponStatusRequest`
- `AttachDiscountablesRequest`

**Resources:**
- `DiscountResource` (show name/description as-is, include coupons/redemptions counts via `withCount`)
- `DiscountCollection`
- `CouponResource`
- `CouponCollection`

### FR-8 — Domain Events (lightweight)

Create under `Modules\Promotion\Events\`:
- `DiscountCreated` (public Discount $discount)
- `DiscountRedeemed` (public DiscountRedemption $redemption)
- `CouponRedeemed` (public DiscountRedemption $redemption) — only dispatched when `coupon_id` is not null.

Use `Dispatchable` + `SerializesModels`. Dispatch `DiscountRedeemed` / `CouponRedeemed` via `DB::afterCommit()` inside `DiscountUsageService::redeem()` so listeners never see an uncommitted redemption.

---

## Non-Functional Requirements

### NFR-1 — Architecture consistency
- Follows existing modular layout exactly (`modules/Promotion/{src,database/*,routes,lang,tests}`).
- All HTTP validation via `FormRequest` classes; zero inline `$request->validate()` or `Validator::make()` in controllers.
- All HTTP responses wrapped in `Resource` / `ResourceCollection`; controllers never return raw arrays.
- Business logic lives in Service classes; controllers resolve services via constructor injection.

### NFR-2 — Test coverage
- Every rule-type AC has at least one passing Pest test.
- Tests use Pest convention (`uses(TestCase::class, RefreshDatabase::class)`).

### NFR-3 — Scramble compliance
- Every controller method has `#[Group]` at the controller class level and one or more `#[Response]` attributes per action with meaningful status codes (200, 201, 401, 403, 404, 422 as applicable).

### NFR-4 — Concurrency safety for redemptions
- `DiscountUsageService::redeem()` MUST use `DB::transaction()` + `lockForUpdate()` on the `Discount` row before re-checking eligibility counts; a concurrent double-redeem against the same user+discount MUST succeed exactly once and fail the other with a clear domain exception.

### NFR-5 — i18n-ready
- Multilingual `name` and `description` JSON columns are exposed via Resource as-is (the client chooses language).
- Validation messages and static strings use `__('promotion::...')` with English fallback defined in `lang/en/validation.php` and Arabic in `lang/ar/validation.php`.

### NFR-6 — No dependency on HTTP for core services
- `DiscountCalculator`, `DiscountEligibilityService`, `CouponValidator`, `DiscountUsageService`, `DiscountResolver` have zero HTTP/Request dependencies and can be called from Artisan commands, listeners, or other modules' services directly.

---

## Acceptance Criteria

All ACs below are typed as `rule` (objective pass/fail) or `rubric` (evaluative with threshold).

### Module Structure & Autoload

- **AC-1 (rule)**: `composer dump-autoload` exits 0 after adding PSR-4 entries for `Modules\Promotion\`, `Modules\Promotion\Database\Factories\`, and `Modules\Promotion\Database\Seeders\`.
- **AC-2 (rule)**: `class_exists(Modules\Promotion\PromotionServiceProvider::class)` returns true, and `bootstrap/providers.php` lists `PromotionServiceProvider::class` in its return array.
- **AC-3 (rule)**: Running `php artisan migrate` (fresh) creates tables `discounts`, `coupons`, `discountables`, `discount_redemptions` with all columns specified in FR-4 and the unique constraint on `coupons.code` and `discountables(discount_id, discountable_type, discountable_id)`.
- **AC-4 (rule)**: Rolling back (`migrate:rollback` for the promotion batch) drops all 4 tables without FK errors.
- **AC-5 (rubric 0-2, ≥2 pass)**: Directory layout matches FR-1 exactly (`src`, `database/migrations`, `database/factories`, `routes`, `lang/en`, `lang/ar`, `tests/Feature`, `tests/Unit`, `tests/Pest.php`).

### Enums

- **AC-6 (rule)**: Each enum (`DiscountType`, `DiscountScope`, `ApplicationMethod`, `PromotionStatus`) is a `BackedEnum` with exact case sets per FR-2; each `->value` matches the spec strings.
- **AC-7 (rule)**: Eloquent models (`Discount.type`, `Discount.scope`, `Discount.application_method`, `Discount.status`, `Coupon.status`) correctly round-trip enum objects via `create()` → `find()` (stored string returns the enum instance with matching `value`).

### Traits applied to existing models

- **AC-8 (rule)**: `Modules\Centers\Models\Center` uses `HasDiscounts` trait; `$center->discounts()` returns a `MorphMany` where `owner_type` resolves to `center` morph key.
- **AC-9 (rule)**: `Modules\Purchase\Models\Purchase` and `Modules\Purchase\Models\PurchaseItem` each use `HasDiscountable` trait; `$purchase->discountRedemptions()` and `$purchaseItem->discountRedemptions()` return `MorphMany` with `discountable` relation name.

### Discount model integrity

- **AC-10 (rule)**: Creating a Discount with `owner = Center` sets `owner_type='center'` and `owner_id=$center->id`; creating one with `owner = Platform` uses `owner_type='platform'`. Both resolve via `$discount->owner` without errors.
- **AC-11 (rule)**: Multilingual `name` stored as JSON `{"en":"10%","ar":"10٪"}` reads back as a PHP array with identical keys; Resource exposes it without flattening.
- **AC-12 (rule)**: A discount with `scope=specific_items` attached to three `HasDiscountable` models creates exactly three `discountables` rows, all with the same `discount_id`, and `$discount->discountables` returns a collection of those 3 morph targets.
- **AC-13 (rule)**: `Coupon.code` column enforces uniqueness: inserting two coupons with the same uppercase normalized code results in a query exception on the second insert.

### DiscountCalculator

- **AC-14 (rule)**: Fixed discount value=50 applied to subtotal=200 → `calculate()` returns exactly `50.00`. Applied to subtotal=30 → returns `30.00` (capped, cannot exceed subtotal).
- **AC-15 (rule)**: Percentage discount value=10 applied to subtotal=200 → returns exactly `20.00`.
- **AC-16 (rule)**: Percentage discount value=10, maximum_discount=5.00 applied to subtotal=200 → returns exactly `5.00` (capped by max).
- **AC-17 (rule)**: Percentage discount value=10 applied to subtotal=0.01 → returns `0.01` (capped at subtotal, no negative result).
- **AC-18 (rule)**: Every returned `discount_amount` has no more than 2 decimal digits (stable rounding).

### DiscountEligibilityService

- **AC-19 (rule)**: Discount with `status=in_active` → `isEligible()` returns false, regardless of other fields.
- **AC-20 (rule)**: Discount with `starts_at` in the future → returns false. Discount with `ends_at` in the past → returns false. Discount where `starts_at <= now <= ends_at` (or ends_at null) → passes the date check.
- **AC-21 (rule)**: Discount with `minimum_amount=100` tested against subtotal=99 → false; subtotal=100 → true (boundary inclusive).
- **AC-22 (rule)**: Discount with `usage_limit=1` against a discount that already has 1 redemption → returns false. Adding the redemption via `DiscountUsageService` within a concurrent call blocks the double-spend (see concurrency AC).
- **AC-23 (rule)**: Discount with `usage_limit_per_customer=1`, user A already redeemed once, user A tests eligibility → false; user B tests → true (user-scoped count).
- **AC-24 (rule)**: Discount with `scope=specific_items` and item X attached; tested with items=[X] → true. Tested with items=[Y] where Y is not attached → false. Empty `$items` with specific_items → false.

### CouponValidator

- **AC-25 (rule)**: Non-existent code → returns `valid=false, reason='coupon_not_found'`.
- **AC-26 (rule)**: Inactive coupon → `reason='coupon_inactive'`. Coupon with `ends_at` in the past → `reason='coupon_expired'`.
- **AC-27 (rule)**: Valid active coupon linked to an *ineligible* discount (e.g. min amount not met) → `reason='discount_ineligible'`.
- **AC-28 (rule)**: Valid active coupon linked to an eligible discount → returns `valid=true` with both `coupon` and `discount` objects hydrated.
- **AC-29 (rule)**: Code comparison is case-insensitive: stored `SAVE10` matches input `save10` (Str::upper normalization).

### DiscountUsageService + concurrency safety

- **AC-30 (rule)**: `redeem()` persists a `DiscountRedemption` row with all required columns; the saved `discount_amount` exactly equals the argument.
- **AC-31 (rule)**: `redeem()` dispatches `DiscountRedeemed` exactly once *after commit* (verifiable via `Event::fake()` and checking DB state inside the `shouldDispatch` callback). If `$coupon` provided, also dispatches `CouponRedeemed`.
- **AC-32 (rule)**: Attempting `redeem()` against a discount that is *already* at `usage_limit` (count check fails under the lock) throws a domain exception and inserts zero rows.
- **AC-33 (rule)**: Attempting `redeem()` for a user who is already at `usage_limit_per_customer` throws a domain exception and inserts zero rows.
- **AC-34 (rule: concurrency)**: When two concurrent calls to `redeem()` race for a single remaining `usage_limit` on the same discount, exactly one call succeeds (one row inserted) and the other throws the limit exception; DB never contains 2 rows (verified by wrapping two sequential transactions with a controlled lock simulation, OR via two closure-based `DB::transaction()` calls in the same test using `DB::beginTransaction()` / manually holding a lock).

### DiscountResolver

- **AC-35 (rule)**: `resolveAutomatic()` with no filters returns only `application_method=automatic` discounts with `status=active` and valid date windows; coupons-only discounts are excluded.
- **AC-36 (rule)**: Passing an `$owner` (e.g. a Center) filters to discounts whose `owner` matches (morph class + id).
- **AC-37 (rule)**: Results from `resolveAutomatic()` pass `DiscountEligibilityService::isEligible()` for the provided arguments (every returned discount is actually eligible when re-checked independently).
- **AC-38 (rule)**: Result ordering is deterministic (stable by `id` ascending; testable with two discounts created at different times with identical eligibility).

### HTTP CRUD layer

- **AC-39 (rule)**: `POST /api/center/discounts` authenticated as a center user creates a discount with `owner_type='center'` and `owner_id` equal to the user's active center (access-scoped). `POST /api/admin/discounts` creates with `owner=Platform`.
- **AC-40 (rule)**: All five input-request classes (`StoreDiscountRequest`, `UpdateDiscountRequest`, `UpdateDiscountStatusRequest`, `StoreCouponRequest`, `AttachDiscountablesRequest`) extend `FormRequest` and have explicit `rules()`; controllers use `$request->validated()` exclusively.
- **AC-41 (rule)**: Attempting to `DELETE /discounts/{id}` on a discount that has at least 1 redemption row returns a 422 with a validation/policy message (delete is restricted).
- **AC-42 (rule)**: `POST /discounts/{id}/coupons` in generator mode `{prefix:'SAVE', quantity:5, length:6, ...}` creates exactly 5 unique coupons, each code starting with `SAVE` prefix and length equal to `6 + strlen(prefix)` (or explicit total length; doc: generated codes = `prefix` + random alphanumeric of `length` chars). All are linked to the target discount.
- **AC-43 (rule)**: `POST /discounts/{id}/discountables` with `[{discountable_type:'purchase_item', discountable_id:1}]` attaches that item; the `discountables` unique constraint prevents attaching duplicates.
- **AC-44 (rule)**: Every controller method has a `#[Group]` at the class level and at least one `#[Response(status:200|201)` plus error `#[Response]` attributes (401, 403, 404, 422 where applicable); Scramble can parse them without exceptions (a `php artisan scramble:generate` exit-code check counts, or at minimum method signatures attribute-checked via reflection).
- **AC-45 (rule)**: Controllers never return a raw `response()->json([...])` payload that is NOT wrapped in a `Resource` or `ResourceCollection` (grep-style check for controller methods returning non-resource responses should return 0 hits).

### Tests

- **AC-46 (rule)**: Running `./vendor/bin/pest modules/Promotion/tests` exits 0 with all tests passing.
- **AC-47 (rubric 0-2, ≥2 pass)**: Every rule-type AC (numbered above) has at least one corresponding passing test case; no rule AC exists without test coverage.

### Architectural & cross-cutting

- **AC-48 (rule)**: None of the 5 core services (Calculator, Eligibility, CouponValidator, Usage, Resolver) depend on `Illuminate\Http\Request` or any HTTP-related interface.
- **AC-49 (rule)**: `DiscountUsageService::redeem()` body wraps all write-work inside exactly one `DB::transaction()` closure and calls `lockForUpdate()` on the Discount row prior to any count-based eligibility check.
- **AC-50 (rubric 0-2, ≥1 pass)**: Code style consistency: Pest tests, Service-based logic, no inline validate in controllers, Resource-wrapped responses, enum casts, JSON for multilingual fields, decimal:2 for money, and `DB::afterCommit()` for redemption events.

## Open Questions
- [ ] Reserved for user review: Should coupon `code` uniqueness be globally unique OR per-discount unique? Current spec: globally unique (simpler checkout UX: one code → one discount). If user prefers per-discount, we can relax to `unique(discount_id, code)`.
- [ ] Reserved for user review: Should `DELETE /discounts/{id}` soft-delete or hard-delete? Spec: hard-delete with restrict when redemptions exist. Alternative: add `softDeletes` for Discount + Coupon.

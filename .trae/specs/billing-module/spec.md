# Billing Module - Product Requirements Document

## Overview
- **Summary**: Implement a modular Laravel Billing module that provides subscription Plans, Feature sets, Limit quotas, and Subscribable (polymorphic) Subscriptions with trial windows, status lifecycle, and an admin CRUD API to manage plans/features/limits and center-facing plan listing + subscription operations.
- **Purpose**: Provide reusable multi-tenant billing primitives so the system can sell subscriptions to Centers (or any Subscribable entity) with feature flags, quota limits, and trial periods.
- **Target Users**:
  - **Platform admins**: who create and administer plans, features, limits and pivot assignments (CRUD).
  - **Center users**: who browse the plan catalog and manage their center's subscriptions.

## Goals
- Deliver `modules/Billing/` Modular Laravel module with:
  - 6 DB tables (plans, features, plan_features pivot, limits, plan_limits pivot, subscriptions), all properly migrated and rollback-able.
  - 4 string-backed enums with exact case sets (BillingInterval, PlanStatus, SubscriptionStatus, SubscriptionAction).
  - Reusable `HasSubscriptions` trait applied to at least `Centers\Models\Center` (morph-to Subscribable).
  - Pure (HTTP-free) Service classes for: PlanFeatureLimitLookup, SubscriptionLifecycleService, SubscriptionEligibilityService, BillingIntervalCalculator.
  - Typed domain events: SubscriptionCreated, SubscriptionCanceled, SubscriptionRenewed, PlanCreated.
  - HTTP APIs:
    - Admin: full CRUD on plans, nested CRUD on plan_features and plan_limits pivots, features and limits CRUD endpoints. All inputs via `FormRequest`, all outputs via `JsonResource` + `ResourceCollection`, every public endpoint with Scramble `#[Group]` + `#[Response(...)]` attributes. Auth guard `auth:sanctum` (Platform admin).
    - Center: plan catalog listing (active only), subscription create / cancel / current-status endpoints. Auth guard `auth:center_user` with owner check.
  - Pest Unit test suite: enum tests, model+migration tests, every service rule test, concurrency/last-slot test for usage limits, Scramble attribute structural tests.
  - Full integration: `composer dump-autoload` succeeds with new PSR-4 entries, `bootstrap/providers.php` registers new provider, `php artisan migrate:fresh` passes, full project test suite 0 regression, hygiene greps clean.
- Conventions 100% match existing modules (Purchase / Promotion / Centers).
- 5-phase Spec Mode workflow artifacts live under `.trae/specs/billing-module/`.

## Non-Goals
- No invoices, receipts, proration, credit notes, VAT, or tax handling. Payment collection is delegated to the existing Purchase module.
- No webhook endpoints or payment callbacks in this module.
- No usage metering / usage counters; this module stores the PLANS, FEATURES, LIMITS and SUBSCRIPTIONS. Reading usage counts of the real system (e.g. number of users) is out of scope.
- No UI / frontend code (backend only).
- No automatic renewal cron implementation; `BillingIntervalCalculator::nextBillingDate(...)` helper is the deliverable for the cron to later consume.
- No `clients` (end-user) subscription API; Subscribable is a polymorphic morph so any model can use the trait later, but only Center endpoints are in scope for FR-7.

## Background & Context
- Project root: `/Users/mac/Herd/khyyal_backend`. Modular Laravel with `modules/{Name}/{src,database,routes,lang,tests}`.
- Existing modules with conventions to mirror: **Centers**, **Purchase**, **Promotion** (Promotion was built just before this; mirror its ServiceProvider, Service classes, Pest layout, trait pattern, FormRequest + Resource + Scramble attribute patterns, enum style, migration `decimal(14,2)+decimal:2` cast rule, `DB::transaction + lockForUpdate + afterCommit` event pattern for lifecycle transitions).
- Auth: Sanctum with 3 guards (`sanctum` generic, `center_user`, `client`) + `center.scope` middleware.
- Morph map already registered by PurchaseServiceProvider: `platform` => Platform, `center` => Center, `client` => Client. `HasSubscriptions::$subscribable` MUST reuse these keys.
- `Illuminate\Database\Eloquent\Relations\Relation::morphMap` preserved; no new keys for existing models.
- Hard constraints from project_memory:
  - Use Scramble `#[Group]` + `#[Response(...)]` on ALL controller public methods.
  - All validation via FormRequest classes.
  - All response payloads via Resource / ResourceCollection.
  - Thin controllers; logic in Service classes only.
  - Money/price in `decimal(14,2)`, cast `decimal:2`, 2-digit rounding.
  - `DB::transaction + lockForUpdate + DB::afterCommit` for any lifecycle transition that writes a Subscription row + emits a domain event.
  - Events dispatch only after successful DB commit.

## Functional Requirements

**FR-1 Module skeleton & integration**
- Module directory: `modules/Billing/{src,database/migrations,database/factories,routes,lang/en,lang/ar,tests/Unit,tests/Feature}`.
- New PSR-4 entries in root `composer.json:autoload.psr-4`:
  - `Modules\Billing\` => `modules/Billing/src/`
  - `Modules\Billing\Database\Factories\` => `modules/Billing/database/factories/`
  - `Modules\Billing\Database\Seeders\` => `modules/Billing/database/seeders/`
- Service provider: `\Modules\Billing\BillingServiceProvider` registered in `bootstrap/providers.php` AFTER PromotionServiceProvider.
- `BillingServiceProvider::boot()` loads: migrations, translations (namespace `billing`), then `require` both `routes/admin.php` and `routes/center.php`.
- `tests/Pest.php` under Billing uses global Tests\TestCase + RefreshDatabase for Unit and Feature subdirs.

**FR-2 Enums (4 string-backed; exact cases)**
All enums placed in `Modules\Billing\Enums\`:
- **BillingInterval**: `monthly`, `yearly`.
- **PlanStatus**: `active`, `in_active`.
- **SubscriptionStatus**: `active`, `canceled`, `expired`, `trialing`.
- **SubscriptionAction**: `subscribe`, `cancel`, `renew`, `expire`.

**FR-3 Traits**
- **HasSubscriptions** trait in `Modules\Billing\Traits\HasSubscriptions`:
  - `subscriptions(): HasMany(Subscription::class, 'subscribable')` (morphTo inverse).
  - `activeSubscriptions(): HasMany` scoped to SubscriptionStatus `active` OR `trialing`.
  - `currentSubscription(): ?Subscription` (latest by starts_at among active/trialing).
  - `subscribe(Plan $plan, array $options): Subscription` delegates to `SubscriptionLifecycleService`.
- Apply trait to `Modules\Centers\Models\Center`.

**FR-4 Database Schema (6 tables)**
All new migrations MUST be prefixed with a consistent timestamp prefix (use `2026_09_28_00000N`) so they run AFTER the 4 Promotion migrations (000001..000004):
1. **plans**: `id`, `name:json{en,ar}`, `slug:string UNIQUE`, `description:json nullable`, `price:decimal(14,2) default 0`, `billing_interval ENUM(BillingInterval) NOT NULL`, `display_features:json nullable`, `status ENUM(PlanStatus) NOT NULL DEFAULT 'active'`, `trial_days:integer UNSIGNED NOT NULL DEFAULT 0`, `created_at`, `updated_at`.
   - INDEX: `plans_status_index(status)`, `plans_billing_interval_index(billing_interval)`, `UNIQUE plans_slug_unique(slug)`.
2. **features**: `id`, `key:string UNIQUE NOT NULL`, `name:string NOT NULL`, `is_quota:boolean NOT NULL DEFAULT false`, `created_at`, `updated_at`.
   - COLUMN NOTE: `is_quota` is the direct DB representation of the diagram's "type: bool". Semantically: true = the feature corresponds to a numeric quota limit (also available via limits/plan_limits), false = boolean on/off capability toggled purely by presence in plan_features pivot.
3. **limits**: `id`, `key:string UNIQUE NOT NULL`, `name:string NOT NULL`, `created_at`, `updated_at`.
4. **plan_features** pivot: `id`, `plan_id FK cascade`, `feature_id FK cascade`, `enabled:boolean NOT NULL DEFAULT true`, `created_at`, `updated_at`.
   - UNIQUE triple `plan_feature_unique(plan_id, feature_id)`.
   - DIAGRAM NOTE: original diagram had a `value` field on plan_features; we store `enabled:boolean` to match boolean-type features, numeric quota values live in plan_limits.
5. **plan_limits** pivot: `id`, `plan_id FK cascade`, `limit_id FK cascade`, `value:integer NOT NULL`, `created_at`, `updated_at`.
   - UNIQUE triple `plan_limit_unique(plan_id, limit_id)`.
6. **subscriptions**: `id`, `subscribable morph (subscribable_type + subscribable_id)`, `plan_id FK restrict (prevents deleting a plan with subscriptions)`, `status ENUM(SubscriptionStatus) NOT NULL DEFAULT 'trialing'`, `trial_ends_at:timestamp nullable`, `starts_at:timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP`, `ends_at:timestamp nullable`, `canceled_at:timestamp nullable`, `created_at`, `updated_at`.
   - INDEXES:
     - `subscriptions_status_index(status)`
     - `subscriptions_ends_at_index(ends_at)`
     - `subscriptions_plan_foreign(plan_id)`
     - `subscriptions_subscribable_type_subscribable_id_index(subscribable_type, subscribable_id)`
   - FK: `subscriptions_plan_id_foreign` => `plans.id` `ON DELETE RESTRICT ON UPDATE CASCADE`.

**FR-5 Models / Factories / Events**
- **Models** in `Modules\Billing\Models\`:
  - **Plan**: fillable + casts match FR-4 exactly; `belongsToMany features via plan_features pivot`; `belongsToMany limits via plan_limits pivot with value`; `hasMany subscriptions`; `hasMany(PlanFeature)` direct; `hasMany(PlanLimit)` direct; `newFactory => PlanFactory`.
  - **Feature**: `belongsToMany plans via plan_features`; `newFactory => FeatureFactory`.
  - **Limit**: `belongsToMany plans via plan_limits with value`; `newFactory => LimitFactory`.
  - **PlanFeature** (MorphPivot-like direct pivot model): `belongsTo plan`, `belongsTo feature`; `timestamps()`.
  - **PlanLimit** (direct pivot model): `belongsTo plan`, `belongsTo limit`; `timestamps()`.
  - **Subscription**: `subscribable morphTo`, `belongsTo plan`, casts for all enums + timestamps; `newFactory => SubscriptionFactory`.
- **Factories** in `database/factories/`: `PlanFactory` (state helpers: monthly, yearly, active, inactive, withTrial(int), withFeature(Feature $feat, bool enabled=true), withLimit(Limit $lim, int $value=10)); `FeatureFactory`; `LimitFactory`; `SubscriptionFactory` (state helpers: active, trialing, canceled, expired, trialEndsAt, endsAtSoon, endsAtPast, canceledDaysAgo(int)).
- **Events** all `Dispatchable + SerializesModels`: `PlanCreated(Plan)`, `SubscriptionCreated(Subscription)`, `SubscriptionCanceled(Subscription)`, `SubscriptionRenewed(Subscription)`.

**FR-6 Pure Services (4 classes)**
All placed in `Modules\Billing\Services\`.
1. **PlanFeatureLimitLookup**:
   - `getFeatures(Plan $plan): Collection<{key:string, name:string, is_quota:bool, enabled:bool}>`
   - `getLimitValue(Plan $plan, string $limitKey, int $default = 0): int`
   - `getAllLimits(Plan $plan): Collection<{key:string, name:string, value:int}>`
   - `planHasFeatureEnabled(Plan $plan, string $featureKey): bool`
2. **BillingIntervalCalculator**:
   - `nextBillingDate(DateTimeInterface $from, BillingInterval $interval, int $count=1): Carbon`
   - `trialEndsAt(Plan $plan, DateTimeInterface $from=null): ?Carbon`
3. **SubscriptionEligibilityService**:
   - `canSubscribe(Model $subscribable, Plan $plan): bool` returns false IF:
     - plan.status != active
     - subscribable already has an active OR trialing subscription for the same plan
   - `canCancel(Subscription $subscription, Model $subscribable=null): bool` returns true only for status in {active, trialing}.
   - `subscriptionCurrentlyValid(Subscription $sub): bool` => status in {active, trialing} AND (ends_at is null OR ends_at >= now).
4. **SubscriptionLifecycleService**:
   - `subscribe(Model $subscribable, Plan $plan, SubscriptionStatus $overrideStatus=null, bool $bypassEligibility=false): Subscription` — DB::transaction wrapping plan row `lockForUpdate` + eligibility re-check via SubscriptionEligibilityService + create subscription row with trial/interval dates via BillingIntervalCalculator + `afterCommit` dispatch SubscriptionCreated(...)
   - `cancel(Subscription $sub, bool $cancelImmediately=false): Subscription` — tx + lock + eligibility re-check; sets status=canceled, canceled_at=now; if cancelImmediately=true also ends_at=now (otherwise set ends_at to original subscription period end, if any); afterCommit dispatch SubscriptionCanceled.
   - `renew(Subscription $sub): Subscription` — tx + lock; creates NEW subscription row for same subscribable+plan with starts_at = previous ends_at (or now if none), status=active; emit SubscriptionRenewed afterCommit.
   - `expire(Subscription $sub): Subscription` — set status=expired, persisted without event (expire is a cron transition, not user action).

**FR-7 HTTP APIs (Admin + Center)**
Auth guards: Admin `auth:sanctum` (platform), Center `auth:center_user`.

URL prefixes & route file ownership:
- `api/admin/plans*` -> routes/admin.php, controller Admin\PlanController
- `api/admin/features*` -> routes/admin.php, controller Admin\FeatureController
- `api/admin/limits*` -> routes/admin.php, controller Admin\LimitController
- `api/center/plans*` -> routes/center.php, controller Center\PlanController
- `api/center/subscriptions*` -> routes/center.php, controller Center\SubscriptionController

Admin PlanController endpoints (FR-7 table exact URLs):
| Method | URL | Action |
|---|---|---|
| GET | /api/admin/plans | index (paginated, status/interval filters, withCount features/limits/subscriptions) |
| POST | /api/admin/plans | store (status auto-active by default, slug from name.en) |
| GET | /api/admin/plans/{plan} | show (with features + limits + withCounts) |
| PUT | /api/admin/plans/{plan} | update |
| DELETE | /api/admin/plans/{plan} | destroy — 422 if any subscriptions exist |
| PATCH | /api/admin/plans/{plan}/status | updateStatus |
| POST | /api/admin/plans/{plan}/features | attachFeatures(items:[{feature_id,enabled?}]) |
| DELETE | /api/admin/plans/{plan}/features/{feature} | detachFeature |
| POST | /api/admin/plans/{plan}/limits | attachLimits(items:[{limit_id,value}]) |
| DELETE | /api/admin/plans/{plan}/limits/{limit} | detachLimit |

Admin FeatureController endpoints (basic CRUD):
GET/POST/GET id/PUT/DELETE/PATCH status

Admin LimitController endpoints (basic CRUD):
GET/POST/GET id/PUT/DELETE (no status enum, limits are on/off by presence + value in pivot)

Center PlanController:
- GET `/api/center/plans`: list plans where status=active (catalog), with features/limits attached.
- GET `/api/center/plans/{plan}`: detail (active only; 404 if inactive).

Center SubscriptionController:
- POST `/api/center/subscriptions` (payload plan_id int): current authenticated user's center subscribes to the plan (delegate to SubscriptionLifecycleService::subscribe).
- DELETE `/api/center/subscriptions/{subscription}`: cancel (delegate cancel, default cancelImmediately=false).
- GET `/api/center/subscriptions/current`: returns `currentSubscription()` for the authenticated user's center with plan+features+limits loaded.
- GET `/api/center/subscriptions`: list all subscriptions for user's center (paginated).

Controller structural rules:
- Each controller class has `#[Group]`.
- Each public method has `#[Response(status, description)]`.
- All inputs via FormRequest (`StorePlanRequest`, `UpdatePlanRequest`, `UpdatePlanStatusRequest`, `AttachFeaturesRequest`, `AttachLimitsRequest`, `StoreFeatureRequest`, `UpdateFeatureRequest`, `StoreLimitRequest`, `UpdateLimitRequest`, `CreateSubscriptionRequest`, `CancelSubscriptionRequest`).
- All outputs via Resources: `PlanResource` + `PlanCollection`, `FeatureResource` + `FeatureCollection`, `LimitResource` + `LimitCollection`, `SubscriptionResource` + `SubscriptionCollection`. Enums output as `->value` string. withCounts rendered via `->whenCounted(...)`.

**FR-8 Language files**
- `modules/Billing/lang/en/validation.php`, `modules/Billing/lang/ar/validation.php`:
  - `plan_has_subscriptions`, `plan_not_active`, `subscription_ineligible`, `trial_exceeds_max`, `limit_missing_value`, `feature_not_found`, `billing_interval_invalid`, `status_invalid`, `subscribable_owner_mismatch`, `duplicate_plan_feature`, `duplicate_plan_limit` — English + Arabic translations.

## Non-Functional Requirements
- **NFR-1 Architecture match**: thin controllers; Services encapsulate logic; `DB::transaction + lockForUpdate + DB::afterCommit` for subscribe/cancel/renew lifecycle state transitions.
- **NFR-2 Scramble coverage**: every HTTP controller has `#[Group]`; every public method on every controller has at least one `#[Response]`.
- **NFR-3 Money handling**: Plan price stored as `decimal(14,2)` with cast `decimal:2`; future price derived calculations round to 2 decimals.
- **NFR-4 Multilingual JSON**: plan name & description stored as `{en, ar}` JSON; cast `array`. Resources expose the raw JSON object to clients (no flattening).
- **NFR-5 Testability**: no HTTP in domain services; no static facade usage inside services where constructor injection is viable; all Service public methods idempotent enough to be faked via Event::fake / transaction rollbacks in Pest tests.
- **NFR-6 Scope hygiene**: The module MUST NOT reference Promotion module classes (Discount, Coupon, Reservation, Subscription (ok obviously own Billing\Subscription)) or any SaaS/Reservation-specific terms outside its own domain. grep: `Reservation` absent anywhere in `modules/Billing/src/`; Promotion `Discount*` absent.
- **NFR-7 Concurrency**: Concurrent subscribe calls on last-available (no duplicate active subscriptions allowed by business rule) correctly — the lockForUpdate + eligibility re-check within a single DB::transaction ensures exactly one of two racing same-plan same-subscribable subscribe calls succeeds while the other throws `SubscriptionIneligibleException` (RuntimeException subclass).

## Constraints
- **Technical**:
  - Modular Laravel directory layout; PSR-4 autoload entries under root composer.json manually edited.
  - All existing hard constraints from project_memory apply verbatim.
  - Morph keys must reuse the existing `platform/center/client` registered map.
  - Subscriptions.plan_id FK `ON DELETE RESTRICT`. All cascade FKs elsewhere on pivot tables.
  - `slug` for plans must be globally unique. Generate slugs using `Str::slug(name.en)` on create; dedupe with numeric suffix on collision like Promotion module's CenterRegisterService.
  - `trial_days` unsigned integer max value `365`.
  - `plan_limits.value` integer; negative values NOT allowed.
  - No softDeletes anywhere; hard delete except subscriptions restrict case.
- **Business**:
  - Plan status `in_active` makes the plan unpurchasable.
  - One subscribable may have multiple subscriptions but only one active/trialing per plan id at any time.
  - Cancel without `cancelImmediately=true` keeps subscription in status `canceled` but still usable until `ends_at` (access logic separate from service).
  - Trial does not require card; trialing status maps valid=true in eligibility.
- **Dependencies**:
  - Existing modules Promotion, Purchase, Centers, Clients, Support must continue working 100% (existing 243 tests must still pass).
  - Composer dependencies no new packages; everything from existing setup.

## Assumptions
- `subscribable_type` values limited to morph map keys already registered; no new morph keys introduced.
- `features.type: bool` on whiteboard = column `is_quota boolean NOT NULL DEFAULT false` (interpreting diagram).
- Slug collision resolution: `-1`, `-2` suffix on base slug, as in centers.slug generator.
- No usage counters; the limits stored are "quota caps". Future metering logic will consume PlanFeatureLimitLookup, but this module just stores the reference values.
- No expiration cron; service exposes `expire()` method for future cron to call.
- `billing_interval` values are only monthly/yearly (no weekly/quarterly per diagram's unspecified scope).
- Admin owner (platform) = any user authenticated via guard `sanctum` is considered platform admin. No additional Spatie role check for now (matches promotion module simple approach).
- Center ownership: For center endpoints, the authenticated user (guard `center_user`) owns the first assigned center (mirrors promotion's CenterDiscountController pattern; abort 403 if no assignment).

## Acceptance Criteria

### AC-1 (rule): FR-1 skeleton verified
- **Given**: Fresh git clone of the module changes.
- **When**: `composer dump-autoload` exit 0 + `require` of `PromotionServiceProvider::class` followed by `BillingServiceProvider::class` in bootstrap/providers.php.
- **Then**: `php artisan package:discover --ansi` succeeds and `class_exists(\Modules\Billing\BillingServiceProvider::class)` returns true.
- **Pass Condition**: All 3 actions succeed without changes.
- **Evidence**: Copy of shell stdout + class_exists assertion output.

### AC-2 (rule): FR-2 Enum exact case sets
- **Given**: Module loaded.
- **When**: Reflection of 4 enum classes.
- **Then**: Case values exactly match FR-2 (BillingInterval 2 cases, PlanStatus 2, SubscriptionStatus 4, SubscriptionAction 4).
- **Pass Condition**: `EnumTest.php` shows 4 green checks with case counts + string values.
- **Evidence**: Pest results for modules/Billing/tests/Unit/EnumTest.php.

### AC-3 (rule): FR-3 HasSubscriptions trait applied
- **Given**: Centers module unchanged except trait addition.
- **When**: `(new Center)->subscriptions()` returns HasMany with morph keys.
- **Then**: `$center->subscriptions` returns a Subscription collection with subscribable_type = `center`.
- **Pass Condition**: Unit tests verifying trait methods and morph class resolution pass.
- **Evidence**: ModelStructureTest 1 assertion.

### AC-4 (rule): FR-4 6-table migrations, column+index+FK correctness + rollback
- **Given**: Empty test DB (sqlite :memory:).
- **When**: `php artisan migrate` then reverse step-by-step rollback last 6 migrations.
- **Then**: Every column name in FR-4 list present; correct enum types present in Schema; slug unique, pivot unique triples; subscriptions.plan_id FK type = RESTRICT.
- **Pass Condition**: ModelStructureTest 7/7 green; Schema inspector confirms.
- **Evidence**: Pest `ModelStructureTest` result.

### AC-5 (rule): FR-5 Models / Factories / Events structure
- **Given**: Module loaded.
- **When**: Instantiate every model via `::factory()->create()`.
- **Then**: Each model creates successfully; PlanFactory state helpers (monthly/yearly/active/inactive/withTrial/withFeature/withLimit) all work; Events are Dispatchable and fire with correct model payload.
- **Pass Condition**: Model tests: creating 1 Plan + 1 Feature + 1 Limit + a plan_feature pivot + a plan_limit pivot + a Subscription for a Center subscriber all persist correctly. Event::fake assertions pass.
- **Evidence**: Unit tests green.

### AC-6 (rule): FR-6.1 PlanFeatureLimitLookup correctness
- **Given**: Plan with features A,B enabled, quota_limit C (value: 50), absent_limit D (value default 0).
- **When**: Service methods run.
- **Then**: planHasFeatureEnabled for A=true, B=true, absent=false; getAllLimits contains C->50; getLimitValue for D returns 0 default.
- **Pass Condition**: 4 boolean + 2 integer assertions pass.
- **Evidence**: Unit tests on service.

### AC-7 (rule): FR-6.2 BillingIntervalCalculator dates
- **Given**: starts_at 2026-01-15, monthly interval.
- **When**: nextBillingDate(monthly * 3) + trialEndsAt plan trial_days=14.
- **Then**: monthly+3 -> 2026-04-15 00:00:00 same time of day; trialEndsAt = starts_at + 14 days exactly.
- **Pass Condition**: Exact Carbon equality.
- **Evidence**: Unit test on service.

### AC-8 (rule): FR-6.3 SubscriptionEligibilityService checks
- **Given**: Plan INACTIVE, subscribable without active subscriptions.
- **When**: canSubscribe, canCancel, subscriptionCurrentlyValid on various fixtures.
- **Then**: canSubscribe(plan inactive)=false; canSubscribe(same subscribable already trialing same plan)=false; canCancel(status=expired)=false; subscriptionCurrentlyValid(expired, ends_at past)=false.
- **Pass Condition**: 4 boolean assertions green.
- **Evidence**: Unit tests on service.

### AC-9 (rule): FR-6.4 SubscriptionLifecycleService subscribe action (happy path)
- **Given**: Center subscribable, Plan active.
- **When**: call subscribe(center, plan).
- **Then**: 1 new subscription row inserted with subscribable_type/owner match; status trialing if trial_days>0; trial_ends_at populated correctly; ends_at populated via nextBillingDate; SubscriptionCreated event dispatched exactly once only after commit.
- **Pass Condition**: DB rows + Event::fake assertions pass.
- **Evidence**: Unit test green.

### AC-10 (rule): FR-6.4 cancel action (at period end)
- **Given**: Active subscription with ends_at 2026-12-31.
- **When**: cancel(sub, cancelImmediately=false).
- **Then**: status=canceled; canceled_at=now; ends_at unchanged (=2026-12-31); SubscriptionCanceled event afterCommit.
- **Pass Condition**: DB row + event fire.
- **Evidence**: Unit test green.

### AC-11 (rule): FR-6.4 cancel action (immediate)
- **Given**: Active subscription ends_at=future.
- **When**: cancel(sub, cancelImmediately=true).
- **Then**: status=canceled; canceled_at=now; ends_at <= now; event dispatched once.
- **Pass Condition**: DB row + event fire.
- **Evidence**: Unit test green.

### AC-12 (rule): FR-6.4 renew action
- **Given**: Expiring Subscription (ends_at = 2026-12-31, status=active).
- **When**: renew(sub).
- **Then**: NEW subscription row with same plan/subscribable; starts_at = original ends_at; status=active; SubscriptionRenewed afterCommit.
- **Pass Condition**: DB rows + event fire.
- **Evidence**: Unit test green.

### AC-13 (rule): NFR-7 Concurrency race-safe subscribe (1 success, 2nd throw)
- **Given**: Plan active, subscribable without prior subscription. Two sequential subscribe calls wrapped in transactions both requesting lockForUpdate and eligibility re-check.
- **When**: First tx commits (succeeds and increments); second tx runs.
- **Then**: Exactly 1 success, 1 throws `SubscriptionIneligibleException`; DB rows count=1.
- **Pass Condition**: ConcurrencyTest returns 1/1 counts.
- **Evidence**: Pest test green.

### AC-14 (rule): FR-7 Admin Plan CRUD structural
- **Given**: Admin middleware auth:sanctum active.
- **When**: All 10 Admin Plan endpoints; controller visited with valid payloads; status/patch etc.
- **Then**: 200/201/204 codes; resources correct; destroy when subscriptions exist returns 422; status update correct enum values.
- **Pass Condition**: Feature/Unit tests on controller endpoints passing.
- **Evidence**: Test outputs.

### AC-15 (rule): FR-7 Admin Feature and Limit CRUD structural
- **Given**: Admin middleware.
- **When**: Create feature + attach to plan; Create limit + attach with value; detach.
- **Then**: Pivot rows correctly added and removed; unique pivot violation produces 422.
- **Pass Condition**: 2 green controller tests.

### AC-16 (rule): FR-7 Center Plan catalog
- **Given**: Plans active (3) and in_active (1).
- **When**: center user GET /api/center/plans (token valid).
- **Then**: Only 3 active plans returned; payloads via PlanCollection; features attached via whenLoaded.
- **Pass Condition**: Count + in_active excluded filter test green.

### AC-17 (rule): FR-7 Center Subscribe + Cancel
- **Given**: Active plan, valid center token.
- **When**: POST /api/center/subscriptions with valid plan_id.
- **Then**: 201 + SubscriptionResource + DB row + afterCommit SubscriptionCreated.
- **Pass Condition**: Feature test assertions.

### AC-18 (rubric): NFR-2 Scramble attribute completeness
- **Dimension**: All public controller methods have at least one #[Response], all controllers have #[Group].
- **Scale**: 0-5
- **Anchors**: 0 = >5 methods missing; 3 = 1-2 missing; 5 = 0 missing (100%)
- **Pass Threshold**: >= 5
- **Evidence**: ScrambleAttributesTest reflection on 5 controllers passes 100% coverage.

### AC-19 (rule): NFR-6 Scope hygiene
- **Given**: entire modules/Billing/src directory.
- **When**: grep `Reservation` and grep `Discount` (Promotion class names).
- **Then**: 0 hits except no false positives (own Subscription ok).
- **Pass Condition**: Grep command output empty.
- **Evidence**: Terminal output.

### AC-20 (rule): No breaking changes in pre-existing modules (zero regression)
- **Given**: Full project.
- **When**: `php artisan test`.
- **Then**: All existing 243 tests still passing (test count must be >= previous). Any new added tests fine; but zero Purchases/Centers/Clients/Support tests failing due to trait addition on Center.
- **Pass Condition**: `php artisan test` exit 0, test count >= 243.
- **Evidence**: Full run terminal output.

### AC-21 (rule): migrate:fresh on real DB config
- **Given**: Default environment.
- **When**: `php artisan migrate:fresh`.
- **Then**: Exit 0, no SQL errors.
- **Pass Condition**: Return code 0.
- **Evidence**: Command output.

### AC-22 (rule): Pivot unique constraints reject duplicate
- **Given**: Plan + feature already attached.
- **When**: Attaching second time.
- **Then**: Either controller idempotently succeeds (upsert) OR throws 422; never inserts duplicate row. The implementation MUST use upsert matching the plan_feature_unique and plan_limit_unique indexes (idempotent update rather than 422 error).
- **Pass Condition**: After two identical attach POSTs, pivot row count=1 and updated_at timestamp newer.
- **Evidence**: Unit test.

### AC-23 (rule): Slug uniqueness on Plan create
- **Given**: Two plans with identical English name.
- **When**: Create plan via service or admin controller.
- **Then**: Slug collision resolved; Plan 1 slug `my-plan`, Plan 2 slug `my-plan-1`.
- **Pass Condition**: Unit test on slug generator.

### AC-24 (rubric): Overall architecture + conventions match
- **Dimension**: Match to existing Promotion module structure (Service classes, FormRequest + Resource pattern, Scramble attributes, factory state helpers, Pest layout, migrations ordering, decimal columns, enum casts, DB transaction pattern).
- **Scale**: 0-5
- **Anchors**: 0 = significant deviations; 3 = 80% match; 5 = identical conventions everywhere.
- **Pass Threshold**: >= 4
- **Evidence**: Manual code review comparing modules/Promotion vs modules/Billing directory structure + patterns.

## Open Questions
- [ ] **Q1**: `billing_interval` values — are `monthly` + `yearly` sufficient? (no weekly, quarterly) — default `monthly+yearly` implemented per this document.
- [ ] **Q2**: `features.type: bool` on drawing — default column `is_quota` bool NOT NULL default false; confirm? Default interpretation accepted.
- [ ] **Q3**: Who are platform admin users? — Sanctum guard authenticated users without a specific Spatie role check now; matches promotion. Confirm if a permission like 'manage billing' required? Default: no permission gate (just auth:sanctum).
- [ ] **Q4**: Subscribable scope — Do Clients also need subscriptions now, or only Centers? Default: trait applied to Center only (still polymorphic for future), client-side endpoints not built.
- [ ] **Q5**: Plan deletion with subscriptions — should we block via RESTRICT FK (current design) or mark canceled+set deleted_at, or force? Default: RESTRICT FK + controller returns 422 when any subscription exists.

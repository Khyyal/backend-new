# Billing Module - Implementation Plan

## Task 1: Module skeleton, PSR-4 registration, ServiceProvider boot, routes placeholders + Pest.php + lang files
- **Status**: `pending`
- **Priority**: high
- **Depends On**: None
- **Description**:
  - Add 3 PSR-4 entries in root `composer.json:autoload.psr-4` for `Modules\Billing\`, Factories, Seeders.
  - Register `BillingServiceProvider::class` in `bootstrap/providers.php` after Promotion provider.
  - Create ServiceProvider with `loadMigrationsFrom`, `loadTranslationsFrom('billing')`, `require __DIR__.'/../routes/admin.php'` and center routes.
  - Placeholder route files with empty group stubs.
  - `tests/Pest.php` with `uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/Unit');` plus Feature mirror.
  - Create lang/en/validation.php + lang/ar/validation.php with the 12 translation keys listed in FR-8.
- **Acceptance Criteria Addressed**: AC-1, AC-21 foundation, AC-20 zero-regression prerequisite (autoload).
- **Test Requirements**:
  - `rule` TR-1.1: `composer dump-autoload` exit 0 + `class_exists(BillingServiceProvider::class)` true after `artisan package:discover`.
  - `rule` TR-1.2: `lang('billing::validation.plan_has_subscriptions')` returns non-empty string.
  - `rubric` TR-1.3: Directory structure completeness. Scale 1-5; 1=partial dirs, 3=most, 5=all; Threshold >= 5. Evidence: `find modules/Billing -type d` listing.
- **Notes**: Use `mkdir -p` then write files; Promotion module pattern.

## Task 2: Enums (4 exact case sets)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - `BillingInterval` (monthly, yearly)
  - `PlanStatus` (active, in_active)
  - `SubscriptionStatus` (active, canceled, expired, trialing)
  - `SubscriptionAction` (subscribe, cancel, renew, expire)
  - All string-backed, placed in `src/Enums/`.
- **Acceptance Criteria Addressed**: AC-2.
- **Test Requirements**:
  - `rule` TR-2.1: 4 enum classes each have exact case count + string value matches FR-2.
  - `rule` TR-2.2: All 4 are BackedEnum subclasses.
- **Notes**: Promotion EnumTest mirror.

## Task 3: HasSubscriptions trait + apply to Center model (cross-module edit)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1, Task 2
- **Description**:
  - Create `src/Traits/HasSubscriptions.php`: `subscriptions()`, `activeSubscriptions()`, `currentSubscription()`, `subscribe()` delegates to Lifecycle service via app(Service).
  - Add `use HasSubscriptions;` to `modules/Centers/src/Models/Center.php`.
- **Acceptance Criteria Addressed**: AC-3, AC-20 (zero-regression by adding trait without breaking Center relations).
- **Test Requirements**:
  - `rule` TR-3.1: `CenterFactory::new()->create()->subscriptions()->getRelated()` instanceOf Subscription.
  - `rule` TR-3.2: `$center->currentSubscription()` returns null when none; returns latest when present.
- **Notes**: Avoid circular dependency; trait resolves LifecycleService via container at runtime.

## Task 4: 6 Database migrations + indexes + FK + unique triples (ensure ORDER after promotion migrations)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Migration 0005: plans, 0006: features, 0007: limits, 0008: plan_features pivot, 0009: plan_limits pivot, 0010: subscriptions.
  - Prefix filenames `2026_09_28_00000[5-10]_*`.
  - Implement exact FR-4 column spec, indexes, unique triples, RESTRICT FK on subscriptions.plan_id, CASCADE on pivot FK, Cascade UPDATE.
  - `decimal(14,2)` for price. `json` for name/description/display_features. `enum` types match FR-2.
- **Acceptance Criteria Addressed**: AC-4, AC-21 migrate:fresh pass.
- **Test Requirements**:
  - `rule` TR-4.1: `Schema::getColumnListing` matches FR-4 for all 6 tables.
  - `rule` TR-4.2: 6-step rollback drops 6 tables exactly, leaving 4 Promotion tables in memory DB.
  - `rule` TR-4.3: `migrate:fresh` exit 0 on real DB config (sqlite test env).
- **Notes**: Mirror Promotion migrations exactly.

## Task 5: Eloquent Models (6) + Pivot Direct Models (2) total 8
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 4, Task 2
- **Description**:
  - `Plan` — `belongsToMany(Feature::class, 'plan_features', 'plan_id', 'feature_id')->withPivot('enabled')->withTimestamps();` same for limits.
  - `Feature`, `Limit` standard.
  - `PlanFeature` pivot extends `Pivot` with timestamps, `belongsTo(Plan)`, `belongsTo(Feature)`.
  - `PlanLimit` pivot with value; belongsTo; timestamps.
  - `Subscription` — `subscribable` morphTo, `plan` belongsTo, `casts` enum+dates.
  - Each `newFactory()` pointing to its factory.
- **Acceptance Criteria Addressed**: AC-5.
- **Test Requirements**:
  - `rule` TR-5.1: `Plan::factory()->withTrial()->create()->trial_days` equals provided.
  - `rule` TR-5.2: `Plan::factory()->hasAttached(Feature + Limit)…` pivot persisted; relation `->pivot->enabled` or `->pivot->value` readable.
  - `rule` TR-5.3: Subscription morphs `subscribable` correctly for Center.
- **Notes**: Direct pivot-model approach (not morphToMany) keeps attach logic simple for upsert (PlanFeature::upsert).

## Task 6: Factories (4 main + pivot via Service)
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 5
- **Description**:
  - `PlanFactory` states: monthly, yearly, active, inactive, withTrial(days=14), withFeature($feat, enabled=true), withLimit($lim, $value=10).
  - `FeatureFactory`, `LimitFactory`, `SubscriptionFactory` states: active, trialing, canceled, expired, trialEndsAt, endsAtSoon, endsAtPast, canceledDaysAgo.
- **Acceptance Criteria Addressed**: AC-5 structure.
- **Test Requirements**:
  - `rule` TR-6.1: Each factory creates a row in expected table; ModelStructureTest verifies counts.
- **Notes**: `forOwner` on Subscription via morph.

## Task 7: Domain Services Part A — Lookup + Calculator
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 5
- **Description**:
  - `PlanFeatureLimitLookup` implements FR-6.1 getFeatures, getAllLimits, planHasFeatureEnabled, getLimitValue.
  - `BillingIntervalCalculator` implements FR-6.2: nextBillingDate using Carbon addMonths/addYears exact day; trialEndsAt adds trial_days to from (or now).
- **Acceptance Criteria Addressed**: AC-6, AC-7.
- **Test Requirements**:
  - `rule` TR-7.1: AC-6 assertions 4 boolean + 2 integers pass.
  - `rule` TR-7.2: AC-7 Carbon equality tests pass.
- **Notes**: Services are pure; use constructor injection when possible.

## Task 8: Domain Services Part B — Eligibility + Lifecycle (with tx + lock + events)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 7, Task 5, Task 6
- **Description**:
  - `SubscriptionEligibilityService` implements FR-6.3 3 boolean queries.
  - `SubscriptionLifecycleService` implements FR-6.4 subscribe/cancel/renew/expire.
  - `DB::transaction + lockForUpdate + afterCommit` pattern for subscribe/cancel/renew.
  - Create new exception class `SubscriptionIneligibleException` extends RuntimeException under `src/Exceptions/`.
- **Acceptance Criteria Addressed**: AC-8, AC-9, AC-10, AC-11, AC-12, NFR-1, NFR-7.
- **Test Requirements**:
  - `rule` TR-8.1: EligibilityService 4 booleans match AC-8.
  - `rule` TR-8.2: subscribe action creates subscription row + Event::fake SubscriptionCreated once post-commit.
  - `rule` TR-8.3: cancel at-period-end vs immediate produce correct status/ends_at/canceled_at + event.
  - `rule` TR-8.4: renew produces NEW row with starts_at = previous ends_at.
- **Notes**: Mirror Promotion DiscountUsageService transaction+lock shape exactly.

## Task 9: Domain Events
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 5
- **Description**:
  - `PlanCreated`, `SubscriptionCreated`, `SubscriptionCanceled`, `SubscriptionRenewed`.
  - All `use Dispatchable, SerializesModels;` constructor takes the model.
- **Acceptance Criteria Addressed**: AC-5, AC-9, AC-10, AC-11, AC-12.
- **Test Requirements**:
  - `rule` TR-9.1: Event::fake and check dispatch counts in task 8 tests. Events serializable via `var_export` without error.

## Task 10: FormRequest classes
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 2
- **Description**:
  - StorePlanRequest, UpdatePlanRequest, UpdatePlanStatusRequest, AttachFeaturesRequest, AttachLimitsRequest
  - StoreFeatureRequest, UpdateFeatureRequest, StoreLimitRequest, UpdateLimitRequest
  - CreateSubscriptionRequest, CancelSubscriptionRequest
- **Acceptance Criteria Addressed**: FR-7 validation, NFR-1 FormRequest pattern.
- **Test Requirements**:
  - `rule` TR-10.1: `StorePlanRequest` validates `billing_interval enum`, `price numeric`, `name required array en/ar`, `trial_days max:365 integer`.
  - `rule` TR-10.2: AttachLimitsRequest enforces `items.*.limit_id` required + `items.*.value` required integer>=0.
  - `rule` TR-10.3: CreateSubscriptionRequest requires `plan_id` integer (existing plans).
- **Notes**: Laravel 11 FormRequest `extendValidator` if needed.

## Task 11: Resource classes + translations check
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 5
- **Description**:
  - `PlanResource`, `PlanCollection`; enum ->value output; `whenCounted` features/limits/subscriptions; features `whenLoaded`, limits `whenLoaded`.
  - `FeatureResource`, `FeatureCollection`; `LimitResource`, `LimitCollection`.
  - `SubscriptionResource`, `SubscriptionCollection`; with plan loaded; status/interval as strings.
  - Translation keys 12 populated (en/ar already created in task 1).
- **Acceptance Criteria Addressed**: FR-7 response conventions.
- **Test Requirements**:
  - `rule` TR-11.1: PlanResource->toArray returns status: string (value) not PlanStatus enum object.
  - `rule` TR-11.2: Collection returns 200 + paginated meta when called via controller.
  - `rule` TR-11.3: `$plan->subscription_count` when withCount applied present via whenCounted.

## Task 12: Controllers + Routes (Admin 3 controllers, Center 2 controllers + 2 route files)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 10, Task 11, Task 8, Task 9
- **Description**:
  - 5 Controllers: `Admin/PlanController` (10 actions), `Admin/FeatureController` (CRUD 6), `Admin/LimitController` (CRUD 5), `Center/PlanController` (index+show), `Center/SubscriptionController` (store, destroy cancel, current, index).
  - Replace placeholder route files with actual `Route::` groups under `prefix('api/admin/plans')`, `api/admin/features`, `api/admin/limits`, `api/center/plans`, `api/center/subscriptions`.
  - Apply correct middleware `auth:sanctum` admin; `auth:center_user` center.
  - `#[Group]` on class; `#[Response(...)]` on each public method. All input via FormRequest; all output via Resource/Collection. `DB::transaction + afterCommit` only for writes that emit events.
  - Owner check for center subscriptions: cancel only allowed if subscription belongs to user's center.
  - Upsert (idempotent) for attachFeatures / attachLimits using pivot unique triples (PlanFeature::upsert / PlanLimit::upsert), mirroring promotion's Discountables.
- **Acceptance Criteria Addressed**: FR-7 endpoints, AC-14, AC-15, AC-16, AC-17, AC-22.
- **Test Requirements**:
  - `rule` TR-12.1: route:list shows all expected endpoints with correct middleware.
  - `rule` TR-12.2: attachFeatures idempotent: 2 identical POSTs yields 1 row total.
  - `rule` TR-12.3: Plan admin destroy returns 422 when subscriptions exist.
  - `rule` TR-12.4: Center subscribe endpoint dispatches SubscriptionCreated event once afterCommit.
- **Notes**: Slug collision logic in PlanAdmin store; use Center pattern with `-1` suffix.

## Task 13: Comprehensive Pest Unit test suite (all AC rubrics & rules)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Tasks 2,5,6,7,8,9,10,11,12
- **Description**:
  - Create the following 11 test files:
    1. `EnumTest.php` — 4 enums.
    2. `ModelStructureTest.php` — migrations 6 tables, rollback, factory create, pivot, trait.
    3. `PlanFeatureLimitLookupTest.php` — FR-6.1 rules.
    4. `BillingIntervalCalculatorTest.php` — FR-6.2 rules.
    5. `SubscriptionEligibilityServiceTest.php` — FR-6.3 booleans.
    6. `SubscriptionLifecycleServiceTest.php` — subscribe/cancel/renew actions + events.
    7. `ConcurrencyTest.php` — NFR-7 2 sequential tx subscribe last-slot test.
    8. `EventDispatchTest.php` — 4 events fire count correctly using Event::fake.
    9. `SlugTest.php` — collision resolution (AC-23).
    10. `FormRequestValidationTest.php` — TR-10 rules.
    11. `ScrambleAttributesTest.php` — 100% coverage (AC-18).
- **Acceptance Criteria Addressed**: Every AC rule/rubric at least once.
- **Test Requirements**:
  - `rule` TR-13.1: All test files green; 0 skipped; 0 risky-random inputs.
  - `rubric` TR-13.2: Test intent — rules before rubrics. Scale 1-5. 1=no AC coverage; 3=partial; 5=100% AC covered by at least one green explicit TR. Threshold >= 4.
- **Notes**: Deterministic base fixtures; no reliance on faker-only values for rule tests.

## Task 14: Final Integration + Cross-module regression + hygiene greps
- **Status**: `pending`
- **Priority**: high
- **Depends On**: All tasks 1–13 completed (or resolved cancelled by approval).
- **Description**:
  - Run `composer dump-autoload`, `php artisan migrate:fresh`, full `php artisan test` (all tests).
  - Grep `Reservation` under `modules/Billing/src`. Must be empty. Grep `Discount` under same (except unrelated false positives). Must be empty.
  - Confirm SubscriptionLifecycleService contains one DB::transaction each in subscribe / cancel / renew, uses `lockForUpdate` on plan row before eligibility re-check.
  - Confirm only Admin\PlanController+FeatureController+LimitController and Center\PlanController+SubscriptionController exist (5 controller files total).
  - Final integration smoke tests on a DB instance that already has Centers and Clients existing; trait attach persists correctly.
- **Acceptance Criteria Addressed**: AC-19, AC-20, AC-21, AC-18, AC-4, NFR-1, NFR-7.
- **Test Requirements**:
  - `rule` TR-14.1: `php artisan test` exit 0 AND test count >= previous known 243.
  - `rule` TR-14.2: grep hygiene commands produce empty stdout.
  - `rule` TR-14.3: LifecycleService grep finds 3 DB::transaction, 3 lockForUpdate (or appropriate) + eligibility recheck inside tx.
  - `rubric` TR-14.4: Overall end-to-end quality & smoke test result. Scale 1-5; 5 = no defects no warnings. Threshold >= 4.
- **Notes**: This is the final "green all tasks" marker. Upon pass, Spec Mode transitions from Implement -> Review.

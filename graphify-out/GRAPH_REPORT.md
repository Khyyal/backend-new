# Graph Report - khyyal_backend  (2026-10-04)

## Corpus Check
- 438 files · ~132,582 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 20 file(s) not represented in the graph (top: (none) 16, .example 1, .xml 1)

## Summary
- 2324 nodes · 5147 edges · 184 communities (93 shown, 91 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 41 edges (avg confidence: 0.83)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Purchase & Payment Actions
- Phone OTP Auth
- Gateway HTTP & Exceptions
- Discount Controllers
- Tamara Client
- Payment Factory & Moyasar
- Payment Events
- Purchase Status & Events
- Discount Admin/Center API
- Subscription Status & Events
- Center Controller & Users
- Feature Resources
- Plan Controller
- Client Factory & Profile Tests
- Base Controller & Scramble
- Billing Admin Routes
- Recreational Riding Tests
- Webhook Event Handlers
- Moyasar Normalizers
- App Service Provider
- Subscribable Models & Seeders
- Center Status & Roles
- CreatePayment Tests
- Base Migrations
- Center Registration
- Purchase Factory & Source
- Composer Autoload
- Billing Scramble Tests
- Client Model & Auth
- Plan Factory
- Plan Features Pivot
- Center & CenterUser Models
- Billing Interval & Plan Status
- Subscription Factory
- CreatePurchase Tests
- Frontend Package
- Boost Guidelines Docs
- Subscription & Discount Traits
- Discount Scope & Factory
- Config & Billing Factories
- Feature & Limit Models
- Discount Redemption
- Limit Controller
- Discount Factory States
- Plan Form Requests
- Center Billing Routes
- Coupon Validation & Eligibility
- User Factory & Model
- Device Service
- Concurrency & Cities Tests
- Payment Gateways Spec
- Rating Service
- Coupon Factory
- Database Seeders
- Center & Tag Factories
- Schedule & Service Traits
- Discountable Traits
- Plan & Subscription Events
- Coupon Redemption Events
- Action Logging
- Pest Bootstraps
- Resource Collections
- Discount Calculator
- Payment Created Tests
- Middleware & Providers
- Root Composer
- Subscription Requests
- Payment Gateway Manager
- City Service
- Plan Migrations
- Centers Composer
- Clients Composer
- Payment Method
- Support Composer
- Composer Requirements
- Center User Permissions
- Promotion Status
- Service Type & Riding Service
- City Trait
- Centers OTP Login Spec
- Composer Dev Deps
- Service Factories
- Webhook Event Factory
- Price Option & Service
- Center Cities Tests
- Billing Module Spec
- Composer Scripts
- Promotion Services
- Token & Webhook Migrations
- Webhook Idempotency Tests
- Client Migration & Gender
- Eligibility Tests
- Recreational Riding Spec
- Bootstrap App
- Purchase Domain Model
- Payment & Webhook Pipeline
- Slug Tests
- Rating Factory
- Device Trait
- pestphp/pest-plugin
- Purchase Module Implementation Specifica
- Support API Layer Review
- Carbon\Carbon
- .attachLimits()
- .verify()
- .update()
- .rate()
- .store()
- CenterAccessRequest
- SendLoginOtpRequest
- VerifyLoginOtpRequest
- LoginOtpRequest
- Promotion Module Review
- autoload-dev
- 2026_09_28_000005_create_plans_table
- 2026_09_28_000006_create_features_table
- 2026_09_28_000007_create_limits_table
- 2026_09_28_000009_create_plan_limits_tab
- 2026_09_28_000010_create_subscriptions_t
- .update()
- CancelSubscriptionRequest
- 2026_09_22_114915_create_centers_table
- 2026_09_22_123438_create_tags_table
- 2026_09_22_123844_create_tag_center_tabl
- 2026_09_22_123999_create_users_table
- 2026_09_22_154022_create_center_user_ass
- 2026_09_28_000001_create_discounts_table
- 2026_09_28_000002_create_coupons_table
- 2026_09_28_000003_create_discountables_t
- 2026_09_28_000004_create_discount_redemp
- 2026_09_26_000001_create_purchases_table
- 2026_09_26_000002_create_purchase_items_
- 2026_09_26_000003_create_payments_table
- 2026_09_30_105748_create_services_table
- 2026_09_30_134805_create_recreational_ri
- 2026_10_03_092351_create_schedules_table
- 2026_10_03_102340_create_price_options_t
- 2026_09_22_104900_create_city_table
- 2026_09_22_112615_create_action_logs_tab
- 2026_09_22_144258_create_ratings_table
- 2026_09_23_111656_create_o_t_ps_table
- Illuminate\Validation\Validator
- .mcp.json
- DiscountableFactory
- ActionLogFactory

## God Nodes (most connected - your core abstractions)
1. `CenterFactory` - 77 edges
2. `Payment` - 74 edges
3. `PaymentStatus` - 67 edges
4. `Discount` - 66 edges
5. `ClientFactory` - 63 edges
6. `Plan` - 56 edges
7. `PlanFactory` - 55 edges
8. `PaymentFactory` - 55 edges
9. `Center` - 53 edges
10. `Purchase` - 53 edges

## Surprising Connections (you probably didn't know these)
- `{closure#10}()` --calls--> `Purchase`  [INFERRED]
  modules/Purchase/tests/Unit/CreatePaymentTest.php → modules/Purchase/src/Models/Purchase.php
- `DiscountUsageService` --semantically_similar_to--> `SubscriptionLifecycleService`  [INFERRED] [semantically similar]
  .trae/specs/promotion-module/spec.md → .trae/specs/billing-module/spec.md
- `Laravel Tests GitHub Actions workflow` --conceptually_related_to--> `Pest testing conventions`  [INFERRED]
  .github/workflows/laravel.yml → CLAUDE.md
- `CityController` --inherits--> `Controller`  [EXTRACTED]
  modules/Support/src/Http/Controllers/Center/CityController.php → app/Http/Controllers/Controller.php
- `CityController` --inherits--> `Controller`  [EXTRACTED]
  modules/Support/src/Http/Controllers/Client/CityController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Payment gateway implementations** — fake_gateway, tamara_gateway, moyasar_gateway [EXTRACTED 1.00]
- **Discount resolution flow** — discount_calculator, discount_eligibility_service, coupon_validator, discount_resolver [INFERRED 0.85]
- **Subscription subscribe flow** — plan_feature_limit_lookup, subscription_eligibility_service, billing_interval_calculator, subscription_lifecycle_service [INFERRED 0.85]
- **Khyyal domain modules** — readme_support_module, readme_centers_module, readme_clients_module [EXTRACTED 1.00]

## Communities (184 total, 91 thin omitted)

### Community 0 - "Purchase & Payment Actions"
Cohesion: 0.06
Nodes (21): {closure#1}(), {closure#2}(), PaymentGateway, PaymentStatus, Cancelled, Expired, Failed, Pending (+13 more)

### Community 1 - "Phone OTP Auth"
Cohesion: 0.06
Nodes (18): AuthController, OTP, OtpVerificationService, SMSService, {closure#1}(), {closure#13}(), {closure#16}(), {closure#17}() (+10 more)

### Community 2 - "Gateway HTTP & Exceptions"
Cohesion: 0.08
Nodes (17): PaymentAuthorizationException, PaymentGatewayException, PaymentInitializationException, PaymentProviderUnavailableException, PaymentProviderValidationException, PaymentStateConflictException, PaymentVerificationException, {closure#2}() (+9 more)

### Community 3 - "Discount Controllers"
Cohesion: 0.12
Nodes (7): DiscountController, DiscountController, DiscountResource, Coupon, Discount, Discountable, {closure#1}()

### Community 4 - "Tamara Client"
Cohesion: 0.10
Nodes (23): {closure#1}(), TamaraClient, TamaraGateway, {closure#10}(), {closure#18}(), {closure#8}(), {closure#1}(), {closure#10}() (+15 more)

### Community 5 - "Payment Factory & Moyasar"
Cohesion: 0.14
Nodes (18): PaymentFactory, {closure#1}(), MoyasarClient, MoyasarGateway, {closure#1}(), {closure#11}(), {closure#12}(), {closure#16}() (+10 more)

### Community 6 - "Payment Events"
Cohesion: 0.11
Nodes (18): PaymentCancelled, PaymentExpired, PaymentFailed, PaymentProcessing, PaymentSucceeded, {closure#1}(), {closure#10}(), {closure#11}() (+10 more)

### Community 7 - "Purchase Status & Events"
Cohesion: 0.10
Nodes (27): PurchaseStatus, Cancelled, Completed, Confirmed, Pending, PurchaseCancelled, PurchaseCompleted, PurchaseConfirmed (+19 more)

### Community 8 - "Discount Admin/Center API"
Cohesion: 0.08
Nodes (13): DiscountCreated, {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), AttachDiscountablesRequest, StoreCouponRequest, StoreDiscountRequest (+5 more)

### Community 9 - "Subscription Status & Events"
Cohesion: 0.11
Nodes (14): SubscriptionStatus, Active, Canceled, Expired, Trialing, SubscriptionCanceled, SubscriptionIneligibleException, Subscription (+6 more)

### Community 10 - "Center Controller & Users"
Cohesion: 0.11
Nodes (8): CenterController, Center, User, CenterAuthService, {closure#1}(), {closure#2}(), {closure#3}(), {closure#9}()

### Community 11 - "Feature Resources"
Cohesion: 0.12
Nodes (6): FeatureResource, CenterResource, CenterWithRoleResource, ClientResource, CouponResource, CityResource

### Community 12 - "Plan Controller"
Cohesion: 0.10
Nodes (7): {closure#1}(), PlanController, AttachFeaturesRequest, PlanResource, Plan, PlanFeatureLimitLookup, {closure#4}()

### Community 13 - "Client Factory & Profile Tests"
Cohesion: 0.11
Nodes (27): ClientFactory, {closure#10}(), {closure#11}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+19 more)

### Community 14 - "Base Controller & Scramble"
Cohesion: 0.18
Nodes (6): Controller, AuthController, RegisterController, UserResource, CityController, CityController

### Community 16 - "Recreational Riding Tests"
Cohesion: 0.21
Nodes (26): CenterFactory, PriceOption, RecreationalRiding, Schedule, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}() (+18 more)

### Community 17 - "Webhook Event Handlers"
Cohesion: 0.12
Nodes (15): WebhookEventHandler, FakeWebhookEventHandler, MoyasarWebhookEventHandler, TamaraWebhookEventHandler, WebhookEvent, {closure#1}(), WebhookProcessorService, {closure#1}() (+7 more)

### Community 18 - "Moyasar Normalizers"
Cohesion: 0.08
Nodes (13): MoyasarSourceNormalizer, MoyasarStatusMapper, TamaraStatusMapper, {closure#1}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#2}() (+5 more)

### Community 19 - "App Service Provider"
Cohesion: 0.09
Nodes (8): AppServiceProvider, BillingServiceProvider, CentersServiceProvider, ClientsServiceProvider, PromotionServiceProvider, {closure#1}(), PurchaseServiceProvider, SupportServiceProvider

### Community 20 - "Subscribable Models & Seeders"
Cohesion: 0.10
Nodes (3): TagSeeder, Tag, Support module

### Community 21 - "Center Status & Roles"
Cohesion: 0.07
Nodes (5): CenterStatus, INVISIBLE, VISIBLE, CenterUserRole, Owner

### Community 22 - "CreatePayment Tests"
Cohesion: 0.14
Nodes (17): CreatePayment, {closure#1}(), CreatePurchase, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}() (+9 more)

### Community 24 - "Base Migrations"
Cohesion: 0.12
Nodes (13): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}() (+5 more)

### Community 25 - "Center Registration"
Cohesion: 0.09
Nodes (4): RegisterCenterFormRequest, CenterRegisterService, {closure#1}(), {closure#2}()

### Community 26 - "Purchase Factory & Source"
Cohesion: 0.08
Nodes (8): {closure#1}(), {closure#2}(), PurchaseSource, Center, Client, System, Platform, {closure#8}()

### Community 27 - "Composer Autoload"
Cohesion: 0.08
Nodes (25): psr-4, App\\, Database\\Factories\\, Database\\Seeders\\, Modules\\Billing\\, Modules\\Billing\\Database\\Factories\\, Modules\\Billing\\Database\\Seeders\\, Modules\\Centers\\ (+17 more)

### Community 28 - "Billing Scramble Tests"
Cohesion: 0.15
Nodes (16): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), methodHasAttr() (+8 more)

### Community 29 - "Client Model & Auth"
Cohesion: 0.12
Nodes (6): ProfileController, Client, ClientAuthService, Buyer, IsBuyer, Rater

### Community 30 - "Plan Factory"
Cohesion: 0.14
Nodes (13): PlanFactory, {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#2}(), {closure#3}(), {closure#4}() (+5 more)

### Community 31 - "Plan Features Pivot"
Cohesion: 0.13
Nodes (3): PlanFeature, PlanLimit, {closure#7}()

### Community 32 - "Center & CenterUser Models"
Cohesion: 0.13
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), CenterUser, {closure#3}(), {closure#4}(), ActivationStatus, ACTIVE (+2 more)

### Community 33 - "Billing Interval & Plan Status"
Cohesion: 0.11
Nodes (15): BillingInterval, Monthly, Yearly, PlanStatus, Active, InActive, SubscriptionAction, Cancel (+7 more)

### Community 34 - "Subscription Factory"
Cohesion: 0.15
Nodes (10): SubscriptionFactory, {closure#7}(), {closure#8}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+2 more)

### Community 35 - "CreatePurchase Tests"
Cohesion: 0.11
Nodes (17): {closure#3}(), PurchaseCreated, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}() (+9 more)

### Community 36 - "Frontend Package"
Cohesion: 0.10
Nodes (20): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, optionalDependencies, @laravel/multiplex (+12 more)

### Community 37 - "Boost Guidelines Docs"
Cohesion: 0.10
Nodes (19): Laravel Boost bootstrap instructions, PHP and Composer prerequisites, API Resources and versioning convention, Laravel Boost Guidelines, Laravel Cloud deployment, Pest testing conventions, Laravel Pint formatter, .ai/rules project rules (+11 more)

### Community 38 - "Subscription & Discount Traits"
Cohesion: 0.15
Nodes (5): HasSubscriptions, HasDiscounts, Purchasable, IsPurchasable, Rateable

### Community 39 - "Discount Scope & Factory"
Cohesion: 0.13
Nodes (13): ApplicationMethod, Automatic, Coupon, DiscountScope, All, SpecificItems, DiscountType, Fixed (+5 more)

### Community 40 - "Config & Billing Factories"
Cohesion: 0.11
Nodes (3): {closure#6}(), {closure#7}(), Feature

### Community 41 - "Feature & Limit Models"
Cohesion: 0.14
Nodes (12): FeatureFactory, LimitFactory, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#8}() (+4 more)

### Community 42 - "Discount Redemption"
Cohesion: 0.17
Nodes (8): DiscountRedemptionFactory, {closure#2}(), {closure#3}(), {closure#5}(), {closure#6}(), uCreate(), PurchaseFactory, {closure#14}()

### Community 43 - "Limit Controller"
Cohesion: 0.14
Nodes (5): LimitController, StoreLimitRequest, UpdateLimitRequest, LimitResource, Limit

### Community 45 - "Discount Factory States"
Cohesion: 0.17
Nodes (5): DiscountFactory, {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}()

### Community 47 - "Center Billing Routes"
Cohesion: 0.16
Nodes (3): PlanController, SubscriptionController, SubscriptionResource

### Community 48 - "Coupon Validation & Eligibility"
Cohesion: 0.16
Nodes (9): CouponValidator, DiscountEligibilityService, DiscountResolver, DiscountUsageService, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+1 more)

### Community 50 - "Device Service"
Cohesion: 0.21
Nodes (3): {closure#2}(), Device, DeviceService

### Community 51 - "Concurrency & Cities Tests"
Cohesion: 0.15
Nodes (11): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), CityFactory, {closure#2}(), {closure#4}(), {closure#6}() (+3 more)

### Community 52 - "Payment Gateways Spec"
Cohesion: 0.20
Nodes (12): Payment Gateways Implementation Prompt, Payment Gateways Review, Payment Gateways Spec, Payment Gateways Tasks, Payment gateway exception hierarchy, MoyasarClient, MoyasarStatusMapper, PaymentGateway Contract (+4 more)

### Community 53 - "Rating Service"
Cohesion: 0.18
Nodes (3): RatingController, Rating, RatingService

### Community 55 - "Coupon Factory"
Cohesion: 0.22
Nodes (6): CouponFactory, {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), vCreate()

### Community 56 - "Database Seeders"
Cohesion: 0.22
Nodes (4): DatabaseSeeder, DatabaseSeeder, CitySeeder, DatabaseSeeder

### Community 57 - "Center & Tag Factories"
Cohesion: 0.17
Nodes (4): TagFactory, UserFactory, ScheduleFactory, OTPFactory

### Community 58 - "Schedule & Service Traits"
Cohesion: 0.16
Nodes (5): HasSchedules, HasService, PriceOptionUnit, MINUTE, OPTION

### Community 59 - "Discountable Traits"
Cohesion: 0.17
Nodes (4): HasDiscountable, {closure#5}(), PurchaseItemFactory, PurchaseItem

### Community 60 - "Plan & Subscription Events"
Cohesion: 0.17
Nodes (8): PlanCreated, SubscriptionCreated, SubscriptionRenewed, {closure#2}(), {closure#2}(), {closure#6}(), {closure#2}(), {closure#5}()

### Community 61 - "Coupon Redemption Events"
Cohesion: 0.23
Nodes (6): CouponRedeemed, DiscountRedeemed, DiscountLimitExceededException, DiscountRedemption, {closure#1}(), {closure#2}()

### Community 62 - "Action Logging"
Cohesion: 0.29
Nodes (3): Actionable, ActionActor, ActionLog

### Community 64 - "Resource Collections"
Cohesion: 0.21
Nodes (6): FeatureCollection, LimitCollection, PlanCollection, SubscriptionCollection, CouponCollection, DiscountCollection

### Community 65 - "Discount Calculator"
Cohesion: 0.23
Nodes (8): DiscountCalculator, cCreate(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 66 - "Payment Created Tests"
Cohesion: 0.18
Nodes (7): {closure#2}(), PaymentCreated, {closure#10}(), {closure#2}(), {closure#4}(), {closure#6}(), {closure#7}()

### Community 67 - "Middleware & Providers"
Cohesion: 0.27
Nodes (3): EnsureCenterAccessScope, SetLocaleFromAcceptLanguage, TrackClientSession

### Community 68 - "Root Composer"
Cohesion: 0.15
Nodes (12): autoload, description, extra, laravel, keywords, dont-discover, license, minimum-stability (+4 more)

### Community 70 - "Subscription Requests"
Cohesion: 0.19
Nodes (5): CreateSubscriptionRequest, StorePlanRequest, {closure#2}(), {closure#4}(), {closure#5}()

### Community 71 - "Payment Gateway Manager"
Cohesion: 0.17
Nodes (4): PaymentGatewayManager, {closure#3}(), {closure#5}(), {closure#6}()

### Community 73 - "Plan Migrations"
Cohesion: 0.17
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 74 - "Centers Composer"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Centers\\ (+3 more)

### Community 75 - "Clients Composer"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Clients\\ (+3 more)

### Community 76 - "Payment Method"
Cohesion: 0.17
Nodes (4): PaymentMethod, CashOnArrival, Manual, Online

### Community 78 - "Support Composer"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Support\\ (+3 more)

### Community 79 - "Composer Requirements"
Cohesion: 0.18
Nodes (11): require, dedoc/scramble, laravel/framework, laravel/sanctum, laravel/tinker, php, spatie/laravel-medialibrary, spatie/laravel-permission (+3 more)

### Community 80 - "Center User Permissions"
Cohesion: 0.24
Nodes (3): CenterUserPermissionRoleSeeder, {closure#1}(), CenterUserPermission

### Community 81 - "Promotion Status"
Cohesion: 0.18
Nodes (4): PromotionStatus, Active, InActive, {closure#4}()

### Community 82 - "Service Type & Riding Service"
Cohesion: 0.25
Nodes (6): ServiceType, RecreationRiding, {closure#1}(), {closure#2}(), {closure#3}(), RecreationRidingService

### Community 84 - "Centers OTP Login Spec"
Cohesion: 0.24
Nodes (8): Centers Phone+OTP Login Review, Centers Phone+OTP Login Spec, Centers Phone+OTP Login Tasks, CenterAuthService, Center, Center list and view endpoints, HasSubscriptions trait, OtpVerificationService

### Community 85 - "Composer Dev Deps"
Cohesion: 0.20
Nodes (10): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+2 more)

### Community 86 - "Service Factories"
Cohesion: 0.20
Nodes (3): RecreationalRidingFactory, ServiceFactory, DeviceFactory

### Community 89 - "Price Option & Service"
Cohesion: 0.24
Nodes (3): PriceOptionFactory, {closure#1}(), Service

### Community 90 - "Center Cities Tests"
Cohesion: 0.20
Nodes (6): {closure#2}(), {closure#4}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 91 - "Billing Module Spec"
Cohesion: 0.33
Nodes (7): Billing Module PRD, Billing Module Implementation Plan, BillingIntervalCalculator, PlanFeatureLimitLookup, Plan, SubscriptionEligibilityService, Subscription

### Community 92 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 93 - "Promotion Services"
Cohesion: 0.28
Nodes (9): Coupon, CouponValidator, DiscountCalculator, DiscountEligibilityService, Discount, DiscountRedemption, DiscountResolver, DiscountUsageService (+1 more)

### Community 96 - "Client Migration & Gender"
Cohesion: 0.22
Nodes (5): {closure#1}(), Gender, FEMALE, MALE, OTHER

### Community 98 - "Eligibility Tests"
Cohesion: 0.39
Nodes (7): baseCreate(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 99 - "Recreational Riding Spec"
Cohesion: 0.39
Nodes (7): Recreational Riding Service Review, Recreational Riding Service PRD, Recreational Riding Service Plan, PriceOption, RecreationalRiding, Schedule, Service

### Community 100 - "Bootstrap App"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 101 - "Purchase Domain Model"
Cohesion: 0.25
Nodes (7): Buyer (polymorphic), CreatePurchase action, Merchant / Platform merchant morph, Purchasable (polymorphic), Purchase, PurchaseStateService, PurchaseStatus lifecycle

### Community 102 - "Payment & Webhook Pipeline"
Cohesion: 0.29
Nodes (8): Cash on Arrival payment, CreatePayment action, MoyasarWebhookEventHandler, Payment, PaymentStatus lifecycle, TamaraWebhookEventHandler, WebhookEvent storage and idempotency, WebhookProcessor pipeline

### Community 103 - "Slug Tests"
Cohesion: 0.39
Nodes (7): callSlugResolve(), callStoreResolve(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 106 - "pestphp/pest-plugin"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 107 - "Purchase Module Implementation Specifica"
Cohesion: 0.53
Nodes (5): Purchase Module Implementation Specification, Purchase Module Review, Purchase Module Spec, Purchase Module Tasks, Purchase domain events (10)

### Community 108 - "Support API Layer Review"
Cohesion: 0.47
Nodes (5): Support API Layer Review, Support API Layer Spec, Support API Layer Tasks, CityService, Dedoc Scramble OpenAPI annotations

### Community 120 - "Promotion Module Review"
Cohesion: 0.83
Nodes (3): Promotion Module Review, Promotion Module PRD, Promotion Module Plan

### Community 121 - "autoload-dev"
Cohesion: 0.50
Nodes (4): autoload-dev, psr-4, Modules\\Purchase\\Tests\\, Tests\\

## Knowledge Gaps
- **183 isolated node(s):** `php`, `$schema`, `name`, `type`, `description` (+178 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 739 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **91 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Center` connect `Center Controller & Users` to `Discount Controllers`, `Discount Admin/Center API`, `Base Controller & Scramble`, `2026_09_30_105748_create_services_table`, `App Service Provider`, `Subscribable Models & Seeders`, `Center Status & Roles`, `Client Model & Auth`, `Center & CenterUser Models`, `Subscription Factory`, `Subscription & Discount Traits`, `Center Billing Routes`, `User Factory & Model`, `Device Service`, `Center & Tag Factories`, `Action Logging`, `Middleware & Providers`, `Model Relations`, `City Trait`, `Service Factories`, `Subscription Factory Tests`, `.rate()`?**
  _High betweenness centrality (0.074) - this node is a cross-community bridge._
- **Why does `Plan` connect `Plan Controller` to `Subscription Status & Events`, `Feature Resources`, `Base Controller & Scramble`, `Subscribable Models & Seeders`, `Plan Features Pivot`, `Billing Interval & Plan Status`, `Subscription & Discount Traits`, `Config & Billing Factories`, `Feature & Limit Models`, `Limit Controller`, `Center Billing Routes`, `User Factory & Model`, `Device Service`, `Concurrency & Cities Tests`, `Rating Service`, `Plan & Subscription Events`, `Model Relations`, `Subscription Factory Tests`, `Carbon\Carbon`, `.attachLimits()`?**
  _High betweenness centrality (0.063) - this node is a cross-community bridge._
- **Why does `Discount` connect `Discount Controllers` to `Discount Factory Tests`, `Discount Calculator`, `Eligibility Tests`, `Model Relations`, `Payment Events`, `Discount Scope & Factory`, `Discount Admin/Center API`, `Subscription & Discount Traits`, `Discount Redemption`, `Discount Factory States`, `Coupon Validation & Eligibility`, `Promotion Status`, `Device Service`, `Subscribable Models & Seeders`, `Coupon Factory`, `Discountable Traits`, `Coupon Redemption Events`?**
  _High betweenness centrality (0.059) - this node is a cross-community bridge._
- **What connects `php`, `$schema`, `name` to the rest of the system?**
  _183 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Purchase & Payment Actions` be split into smaller, more focused modules?**
  _Cohesion score 0.05886708626434654 - nodes in this community are weakly interconnected._
- **Should `Phone OTP Auth` be split into smaller, more focused modules?**
  _Cohesion score 0.06289308176100629 - nodes in this community are weakly interconnected._
- **Should `Gateway HTTP & Exceptions` be split into smaller, more focused modules?**
  _Cohesion score 0.0797979797979798 - nodes in this community are weakly interconnected._
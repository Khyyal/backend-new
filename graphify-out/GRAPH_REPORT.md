# Graph Report - khyyal_backend  (2026-10-07)

## Corpus Check
- 457 files · ~135,508 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 20 file(s) not represented in the graph (top: (none) 16, .example 1, .xml 1)

## Summary
- 2557 nodes · 5580 edges · 183 communities (90 shown, 93 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 51 edges (avg confidence: 0.83)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Payment Gateway Contract
- Discount Admin API
- Plan Feature Requests
- Center Factory & Status
- Plan Factory States
- Purchase Factory
- Architecture Specs
- Tamara Client
- Payment Exceptions & HTTP
- Laravel Cloud Deploy Docs
- Payment Factory
- Controller Scaffolding
- Plan Controller
- Client Profile Tests
- Subscription Status
- Discount Factory
- Billing Events
- Plan Pricing Dates
- Temporary Upload Factory
- Morph Models
- Seeders & Permissions
- Moyasar Normalizers
- Webhook Handlers
- Riding Service Tests
- Discount Redemption
- OTP Verification
- Center Resource & Media
- Purchase Platform
- Center Access Controller
- Limit Controller
- Center Services Models
- Center Registration
- Discount Factory States
- Composer Autoload
- Discountable Factories
- Client Routes & Auth
- Coupon Factory
- Create Payment Action
- Discount Morph Traits
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 78
- Community 79
- Community 80
- Community 82
- Community 83
- Community 84
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 100
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 109
- Community 112
- Community 113
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 145
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 179
- Community 182

## God Nodes (most connected - your core abstractions)
1. `CenterFactory` - 79 edges
2. `Payment` - 74 edges
3. `ClientFactory` - 73 edges
4. `PaymentStatus` - 67 edges
5. `Discount` - 66 edges
6. `Center` - 63 edges
7. `Plan` - 56 edges
8. `PlanFactory` - 55 edges
9. `PaymentFactory` - 55 edges
10. `Purchase` - 53 edges

## Surprising Connections (you probably didn't know these)
- `Laravel Tests GitHub Actions Workflow` --semantically_similar_to--> `Test Suite Performance`  [INFERRED] [semantically similar]
  .github/workflows/laravel.yml → .claude/skills/testing-best-practices/rules/performance.md
- `{closure#10}()` --calls--> `Purchase`  [INFERRED]
  modules/Purchase/tests/Unit/CreatePaymentTest.php → modules/Purchase/src/Models/Purchase.php
- `Buyer / Purchasable contracts and traits` --references--> `Centers Module`  [INFERRED]
  .trae/specs/purchase-module/spec.md → README.md
- `Buyer / Purchasable contracts and traits` --references--> `Clients Module`  [INFERRED]
  .trae/specs/purchase-module/spec.md → README.md
- `CityService (getAll, getWithCenters)` --references--> `Support Module`  [INFERRED]
  .trae/specs/support-api-layer/spec.md → README.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **laravel-best-practices rule files** — claude_skills_laravel_best_practices_rules_advanced_queries, claude_skills_laravel_best_practices_rules_architecture, claude_skills_laravel_best_practices_rules_caching, claude_skills_laravel_best_practices_rules_eloquent, claude_skills_laravel_best_practices_rules_queue_jobs, claude_skills_laravel_best_practices_rules_routing [EXTRACTED 1.00]
- **Convention inference flow** — claude_skills_infer_conventions_skill, claude_skills_infer_conventions_references_checklist, claude_skills_infer_conventions_skill_record_rule_workflow, claude_skills_infer_conventions_skill_ai_rules [EXTRACTED 1.00]
- **Testing Best Practices Rule Files** — _claude_skills_testing_best_practices_rules_assertions, _claude_skills_testing_best_practices_rules_endpoint_tests, _claude_skills_testing_best_practices_rules_isolation, _claude_skills_testing_best_practices_rules_naming, _claude_skills_testing_best_practices_rules_test_data, _claude_skills_testing_best_practices_rules_review [EXTRACTED 1.00]
- **Payment Gateway Webhook and Sync Flow** — _trae_documents_payment_gateways_tamara_moyasar_implementation_prompt_webhook_pipeline, _trae_documents_payment_gateways_tamara_moyasar_implementation_prompt_state_transition_safety, _trae_documents_purchase_module_implementation_1_webhookevent, _trae_documents_payment_gateways_tamara_moyasar_implementation_prompt_callback_verification [INFERRED 0.85]
- **Billing Subscription Lifecycle** — _trae_specs_billing_module_spec_hassubscriptions, _trae_specs_billing_module_spec_subscriptionlifecycleservice, _trae_specs_billing_module_spec_subscriptioneligibilityservice, _trae_specs_billing_module_spec_billingintervalcalculator [EXTRACTED 1.00]
- **Payment provider adapter stack (gateway, client, mapper, webhook handler)** — trae_specs_payment_gateways_tamara_moyasar_spec_tamaragateway, trae_specs_payment_gateways_tamara_moyasar_spec_moyasargateway, trae_specs_payment_gateways_tamara_moyasar_spec_statusmappers, trae_specs_payment_gateways_tamara_moyasar_spec_webhookhandlers [EXTRACTED 0.95]
- **Promotion domain services** — trae_specs_promotion_module_spec_discountcalculator, trae_specs_promotion_module_spec_discounteligibilityservice, trae_specs_promotion_module_spec_couponvalidator, trae_specs_promotion_module_spec_discountusageservice, trae_specs_promotion_module_spec_discountresolver [EXTRACTED 0.95]
- **Purchase and payment transactional state flow** — trae_specs_purchase_module_spec_purchasestateservice, trae_specs_purchase_module_spec_paymentstateservice, trae_specs_purchase_module_spec_webhookprocessorservice, trae_specs_purchase_module_spec_purchase_events [EXTRACTED 0.95]

## Communities (183 total, 93 thin omitted)

### Community 0 - "Payment Gateway Contract"
Cohesion: 0.06
Nodes (17): PaymentGateway, PaymentStatus, Cancelled, Expired, Failed, Pending, Processing, Succeeded (+9 more)

### Community 1 - "Discount Admin API"
Cohesion: 0.08
Nodes (16): DiscountCreated, {closure#1}(), {closure#2}(), DiscountController, {closure#1}(), {closure#2}(), DiscountController, AttachDiscountablesRequest (+8 more)

### Community 2 - "Plan Feature Requests"
Cohesion: 0.04
Nodes (12): AttachFeaturesRequest, CancelSubscriptionRequest, UpdateLimitRequest, UpdatePlanRequest, UpdatePlanStatusRequest, CenterAccessRequest, SendLoginOtpRequest, VerifyLoginOtpRequest (+4 more)

### Community 3 - "Center Factory & Status"
Cohesion: 0.06
Nodes (20): {closure#1}(), CenterStatus, INVISIBLE, VISIBLE, CityFactory, HasCity, City, CityService (+12 more)

### Community 4 - "Plan Factory States"
Cohesion: 0.07
Nodes (27): PlanFactory, SubscriptionFactory, {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#2}(), {closure#1}() (+19 more)

### Community 5 - "Purchase Factory"
Cohesion: 0.08
Nodes (31): {closure#1}(), {closure#2}(), PurchaseFactory, PurchaseStatus, Cancelled, Completed, Confirmed, Pending (+23 more)

### Community 6 - "Architecture Specs"
Cohesion: 0.05
Nodes (45): Centers Module, Clients Module, Khyyal Backend (modular Laravel 13 app), Support Module, PaymentGatewayException hierarchy, PaymentGatewayClientHelpers (timeouts, retry, redaction), MoyasarGateway + MoyasarClient (cards, Apple Pay, STC Pay), PaymentGateway contract (7 methods incl. sync, authorize) (+37 more)

### Community 7 - "Tamara Client"
Cohesion: 0.09
Nodes (22): {closure#1}(), TamaraClient, TamaraGateway, PaymentGatewayManager, {closure#10}(), {closure#18}(), {closure#8}(), {closure#1}() (+14 more)

### Community 8 - "Payment Exceptions & HTTP"
Cohesion: 0.08
Nodes (17): PaymentAuthorizationException, PaymentGatewayException, PaymentInitializationException, PaymentProviderUnavailableException, PaymentProviderValidationException, PaymentStateConflictException, PaymentVerificationException, {closure#2}() (+9 more)

### Community 9 - "Laravel Cloud Deploy Docs"
Cohesion: 0.05
Nodes (41): Cloud multi-step checklists, deploying-to-cloud skill, Build and deploy commands, Laravel Cloud CLI (cloud), Cloud Object Storage and file visibility (R2 buckets), Managed queues and scheduler on Cloud, Secrets Manager, Convention detection checklist (dimensions A-J) (+33 more)

### Community 10 - "Payment Factory"
Cohesion: 0.11
Nodes (22): PaymentFactory, {closure#1}(), MoyasarClient, MoyasarGateway, {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}() (+14 more)

### Community 11 - "Controller Scaffolding"
Cohesion: 0.12
Nodes (7): AuthController, RegisterController, EnsureCenterAccessScope, CenterResource, RatingController, SetLocaleFromAcceptLanguage, TrackClientSession

### Community 12 - "Plan Controller"
Cohesion: 0.08
Nodes (5): {closure#1}(), PlanController, PlanResource, Plan, PlanFeatureLimitLookup

### Community 13 - "Client Profile Tests"
Cohesion: 0.09
Nodes (32): ClientFactory, {closure#10}(), {closure#11}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+24 more)

### Community 14 - "Subscription Status"
Cohesion: 0.09
Nodes (17): SubscriptionStatus, Active, Canceled, Expired, Trialing, SubscriptionCanceled, SubscriptionIneligibleException, Subscription (+9 more)

### Community 15 - "Discount Factory"
Cohesion: 0.07
Nodes (18): ApplicationMethod, Automatic, Coupon, DiscountScope, All, SpecificItems, DiscountType, Fixed (+10 more)

### Community 16 - "Billing Events"
Cohesion: 0.11
Nodes (21): SubscriptionCreated, SubscriptionRenewed, PaymentCancelled, PaymentExpired, PaymentFailed, PaymentProcessing, PaymentSucceeded, {closure#1}() (+13 more)

### Community 17 - "Plan Pricing Dates"
Cohesion: 0.07
Nodes (18): {closure#6}(), {closure#7}(), BillingInterval, Monthly, Yearly, PlanStatus, Active, InActive (+10 more)

### Community 18 - "Temporary Upload Factory"
Cohesion: 0.10
Nodes (19): TemporaryUploadFactory, TemporaryUpload, {closure#1}(), {closure#12}(), {closure#14}(), {closure#15}(), {closure#17}(), {closure#18}() (+11 more)

### Community 19 - "Morph Models"
Cohesion: 0.08
Nodes (5): Feature, HasDiscountable, {closure#2}(), {closure#3}(), PurchaseItem

### Community 20 - "Seeders & Permissions"
Cohesion: 0.09
Nodes (9): DatabaseSeeder, CenterUserPermissionRoleSeeder, {closure#1}(), DatabaseSeeder, CenterUserPermission, CenterUserRole, Owner, CitySeeder (+1 more)

### Community 21 - "Moyasar Normalizers"
Cohesion: 0.08
Nodes (15): MoyasarSourceNormalizer, MoyasarStatusMapper, TamaraStatusMapper, {closure#1}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#2}() (+7 more)

### Community 22 - "Webhook Handlers"
Cohesion: 0.12
Nodes (15): WebhookEventHandler, FakeWebhookEventHandler, MoyasarWebhookEventHandler, TamaraWebhookEventHandler, WebhookEvent, {closure#1}(), WebhookProcessorService, {closure#1}() (+7 more)

### Community 23 - "Riding Service Tests"
Cohesion: 0.19
Nodes (24): CenterFactory, PriceOption, Schedule, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}() (+16 more)

### Community 24 - "Discount Redemption"
Cohesion: 0.12
Nodes (13): DiscountRedemptionFactory, CouponRedeemed, DiscountRedeemed, DiscountLimitExceededException, DiscountRedemption, {closure#1}(), {closure#2}(), DiscountUsageService (+5 more)

### Community 25 - "OTP Verification"
Cohesion: 0.10
Nodes (14): OTP, {closure#13}(), {closure#16}(), {closure#17}(), {closure#18}(), {closure#19}(), {closure#21}(), {closure#22}() (+6 more)

### Community 26 - "Center Resource & Media"
Cohesion: 0.10
Nodes (10): Controller, {closure#3}(), {closure#4}(), {closure#5}(), CityController, CityController, MediaController, UploadMediaRequest (+2 more)

### Community 27 - "Purchase Platform"
Cohesion: 0.08
Nodes (18): PurchaseCreated, Platform, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}() (+10 more)

### Community 28 - "Center Access Controller"
Cohesion: 0.12
Nodes (8): CenterController, Center, {closure#1}(), CenterUpdateService, {closure#1}(), {closure#5}(), {closure#2}(), {closure#3}()

### Community 29 - "Limit Controller"
Cohesion: 0.09
Nodes (6): LimitController, StoreLimitRequest, LimitResource, CenterWithRoleResource, UserResource, CouponResource

### Community 31 - "Center Services Models"
Cohesion: 0.15
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#3}(), {closure#4}(), MediaService

### Community 32 - "Center Registration"
Cohesion: 0.09
Nodes (4): RegisterCenterFormRequest, CenterRegisterService, {closure#1}(), {closure#2}()

### Community 33 - "Discount Factory States"
Cohesion: 0.12
Nodes (8): DiscountFactory, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#7}(), {closure#8}(), PurchaseItemFactory

### Community 34 - "Composer Autoload"
Cohesion: 0.08
Nodes (25): psr-4, App\\, Database\\Factories\\, Database\\Seeders\\, Modules\\Billing\\, Modules\\Billing\\Database\\Factories\\, Modules\\Billing\\Database\\Seeders\\, Modules\\Centers\\ (+17 more)

### Community 35 - "Discountable Factories"
Cohesion: 0.12
Nodes (8): DiscountableFactory, PriceOptionFactory, RecreationalRidingFactory, ScheduleFactory, ServiceFactory, ActionLogFactory, DeviceFactory, OTPFactory

### Community 36 - "Client Routes & Auth"
Cohesion: 0.10
Nodes (5): AuthController, ProfileController, VerifyOtpRequest, ClientResource, ClientAuthService

### Community 37 - "Coupon Factory"
Cohesion: 0.13
Nodes (8): CouponFactory, Coupon, CouponValidator, {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), vCreate()

### Community 38 - "Create Payment Action"
Cohesion: 0.10
Nodes (12): {closure#1}(), {closure#2}(), PaymentMethod, CashOnArrival, Manual, Online, PaymentCreated, {closure#10}() (+4 more)

### Community 39 - "Discount Morph Traits"
Cohesion: 0.12
Nodes (6): HasDiscounts, {closure#1}(), Buyer, Purchasable, IsPurchasable, Rateable

### Community 40 - "Community 40"
Cohesion: 0.14
Nodes (16): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), methodHasAttr() (+8 more)

### Community 41 - "Community 41"
Cohesion: 0.13
Nodes (4): PlanFeature, PlanLimit, {closure#7}(), CenterUser

### Community 42 - "Community 42"
Cohesion: 0.18
Nodes (16): CreatePayment, CreatePurchase, {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#4}() (+8 more)

### Community 43 - "Community 43"
Cohesion: 0.10
Nodes (20): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, optionalDependencies, @laravel/multiplex (+12 more)

### Community 44 - "Community 44"
Cohesion: 0.15
Nodes (11): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+3 more)

### Community 46 - "Community 46"
Cohesion: 0.15
Nodes (3): Client, IsBuyer, Rater

### Community 47 - "Community 47"
Cohesion: 0.19
Nodes (3): {closure#2}(), Device, DeviceService

### Community 49 - "Community 49"
Cohesion: 0.13
Nodes (8): MediaStatus, Attached, Temporary, InvalidTemporaryMediaException, {closure#1}(), {closure#3}(), {closure#5}(), {closure#7}()

### Community 50 - "Community 50"
Cohesion: 0.14
Nodes (13): Tamara and Moyasar Gateways Implementation Prompt, MoyasarGateway, PaymentGateway Contract, Payment State Transition Safety, TamaraGateway, Webhook Processing Pipeline, Buyer and Merchant Polymorphism, Payment (+5 more)

### Community 51 - "Community 51"
Cohesion: 0.14
Nodes (7): AttachLimitsRequest, CreateSubscriptionRequest, StorePlanRequest, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 52 - "Community 52"
Cohesion: 0.16
Nodes (8): FeatureFactory, LimitFactory, Limit, {closure#4}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 53 - "Community 53"
Cohesion: 0.12
Nodes (4): FeatureController, StoreFeatureRequest, UpdateFeatureRequest, FeatureResource

### Community 54 - "Community 54"
Cohesion: 0.13
Nodes (5): TagFactory, UserFactory, {closure#1}(), {closure#5}(), {closure#6}()

### Community 55 - "Community 55"
Cohesion: 0.18
Nodes (9): HasSchedules, ServiceType, RecreationRiding, RecreationalRiding, {closure#1}(), {closure#2}(), {closure#3}(), RecreationRidingService (+1 more)

### Community 56 - "Community 56"
Cohesion: 0.24
Nodes (3): Actionable, ActionActor, ActionLog

### Community 57 - "Community 57"
Cohesion: 0.17
Nodes (5): PlanController, FeatureCollection, LimitCollection, PlanCollection, DiscountCollection

### Community 58 - "Community 58"
Cohesion: 0.18
Nodes (3): User, CenterPolicy, CenterAuthService

### Community 60 - "Community 60"
Cohesion: 0.17
Nodes (13): Testing Assertions Rules, Arrange Act Assert, Endpoint Tests Rules, Finding Test Framework Features, Fakes, Mocks, and Determinism, Framework Fakes Preferred Over Mocks, Test Naming and Structure, Test Suite Performance (+5 more)

### Community 61 - "Community 61"
Cohesion: 0.19
Nodes (4): BillingServiceProvider, CentersServiceProvider, ClientsServiceProvider, PromotionServiceProvider

### Community 63 - "Community 63"
Cohesion: 0.20
Nodes (3): CleanupExpiredMedia, {closure#6}(), TemporaryMediaService

### Community 65 - "Community 65"
Cohesion: 0.23
Nodes (8): DiscountCalculator, cCreate(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 66 - "Community 66"
Cohesion: 0.14
Nodes (4): PurchaseSource, Center, Client, System

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (12): autoload, description, extra, laravel, keywords, dont-discover, license, minimum-stability (+4 more)

### Community 68 - "Community 68"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 70 - "Community 70"
Cohesion: 0.23
Nodes (3): SubscriptionController, SubscriptionCollection, SubscriptionResource

### Community 72 - "Community 72"
Cohesion: 0.18
Nodes (11): Cash on Arrival Payment, Billing Module PRD, BillingIntervalCalculator, HasSubscriptions Trait, Plan (Billing), PlanFeatureLimitLookup, Subscription (Billing), SubscriptionEligibilityService (+3 more)

### Community 73 - "Community 73"
Cohesion: 0.17
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 74 - "Community 74"
Cohesion: 0.20
Nodes (9): PlanCreated, {closure#2}(), {closure#1}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+1 more)

### Community 75 - "Community 75"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Centers\\ (+3 more)

### Community 76 - "Community 76"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Clients\\ (+3 more)

### Community 78 - "Community 78"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Support\\ (+3 more)

### Community 79 - "Community 79"
Cohesion: 0.20
Nodes (3): Laravel Security Best Practices, Laravel Validation and Forms Best Practices, Form Request Extraction

### Community 80 - "Community 80"
Cohesion: 0.18
Nodes (11): require, dedoc/scramble, laravel/framework, laravel/sanctum, laravel/tinker, php, spatie/laravel-medialibrary, spatie/laravel-permission (+3 more)

### Community 82 - "Community 82"
Cohesion: 0.24
Nodes (4): HasService, PriceOptionUnit, MINUTE, OPTION

### Community 83 - "Community 83"
Cohesion: 0.29
Nodes (7): DiscountEligibilityService, DiscountResolver, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), rCreate()

### Community 84 - "Community 84"
Cohesion: 0.20
Nodes (10): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+2 more)

### Community 87 - "Community 87"
Cohesion: 0.31
Nodes (7): Centers Phone+OTP Login Review, Centers AuthController, CenterAuthService, Centers CenterController, OtpVerificationService, Centers Phone+OTP Login Spec, Centers Phone+OTP Login Tasks

### Community 88 - "Community 88"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 90 - "Community 90"
Cohesion: 0.22
Nodes (5): {closure#1}(), Gender, FEMALE, MALE, OTHER

### Community 91 - "Community 91"
Cohesion: 0.39
Nodes (7): baseCreate(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 93 - "Community 93"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 94 - "Community 94"
Cohesion: 0.39
Nodes (7): callSlugResolve(), callStoreResolve(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 97 - "Community 97"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 112 - "Community 112"
Cohesion: 0.50
Nodes (4): autoload-dev, psr-4, Modules\\Purchase\\Tests\\, Tests\\

### Community 141 - "Community 141"
Cohesion: 0.50
Nodes (3): ActivationStatus, ACTIVE, INACTIVE

### Community 142 - "Community 142"
Cohesion: 1.00
Nodes (3): AGENTS.md (Laravel Boost bootstrap instructions), CLAUDE.md (Laravel Boost guidelines), Laravel Boost

### Community 146 - "Community 146"
Cohesion: 0.67
Nodes (3): Payment Gateways Independent Review (PASS), Payment Gateways (Tamara + Moyasar) Spec, Payment Gateways Tasks Plan (13 tasks)

### Community 147 - "Community 147"
Cohesion: 0.67
Nodes (3): Promotion Module Review (PASS, 42 tests), Promotion Module Spec, Promotion Module Tasks Plan (14 tasks)

### Community 148 - "Community 148"
Cohesion: 0.67
Nodes (3): Purchase Module Review (PASS, 72 tests), Purchase Module Spec, Purchase Module Tasks Plan (15 tasks)

### Community 149 - "Community 149"
Cohesion: 0.67
Nodes (3): Riding Service Review (PASS, 12 checkpoints), Recreational Riding Create/Update Spec, Riding Service Tasks Plan (3 tasks completed)

### Community 150 - "Community 150"
Cohesion: 0.67
Nodes (3): Support API Layer Review (PASS), Support Module API Layer Spec, Support API Layer Tasks Plan (3 tasks)

## Knowledge Gaps
- **202 isolated node(s):** `php`, `$schema`, `name`, `type`, `description` (+197 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 817 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **93 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Center` connect `Center Access Controller` to `Discount Admin API`, `Center Factory & Status`, `Plan Factory States`, `Community 134`, `Controller Scaffolding`, `Plan Controller`, `Subscription Status`, `Morph Models`, `Center Services Models`, `Discountable Factories`, `Discount Morph Traits`, `Community 45`, `Community 46`, `Community 47`, `Community 54`, `Community 56`, `Community 57`, `Community 58`, `Community 70`, `Community 85`, `Community 95`, `Community 98`, `Community 99`, `Community 106`?**
  _High betweenness centrality (0.066) - this node is a cross-community bridge._
- **Why does `Plan` connect `Plan Controller` to `Community 99`, `Plan Factory States`, `Community 70`, `Community 74`, `Controller Scaffolding`, `Subscription Status`, `Community 47`, `Billing Events`, `Plan Pricing Dates`, `Morph Models`, `Community 52`, `Community 85`, `Community 57`, `Community 62`?**
  _High betweenness centrality (0.058) - this node is a cross-community bridge._
- **Why does `TestCase` connect `Community 64` to `Payment Gateway Contract`, `Center Factory & Status`, `Purchase Factory`, `Tamara Client`, `Payment Exceptions & HTTP`, `Payment Factory`, `Client Profile Tests`, `Billing Events`, `Temporary Upload Factory`, `Moyasar Normalizers`, `OTP Verification`, `Purchase Platform`, `Center Registration`, `Create Payment Action`, `Community 42`, `Community 45`, `Community 48`, `Community 54`, `Community 59`, `Community 66`, `Community 77`, `Community 89`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **What connects `php`, `$schema`, `name` to the rest of the system?**
  _202 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Payment Gateway Contract` be split into smaller, more focused modules?**
  _Cohesion score 0.06394230769230769 - nodes in this community are weakly interconnected._
- **Should `Discount Admin API` be split into smaller, more focused modules?**
  _Cohesion score 0.08294930875576037 - nodes in this community are weakly interconnected._
- **Should `Plan Feature Requests` be split into smaller, more focused modules?**
  _Cohesion score 0.043834015195791935 - nodes in this community are weakly interconnected._
# Graph Report - modules  (2026-10-08)

## Corpus Check
- 429 files · ~70,465 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2749 nodes · 6954 edges · 173 communities (96 shown, 77 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 34 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Payment Events & Tests
- Payment Gateway Contract
- Center & Subscription Controllers
- Plan Features & Limits
- Client Profile Tests
- Tamara Gateway Client
- Purchase Factory
- Plan Factory
- Subscription Events
- Payment Exceptions & HTTP
- Center Auth & Registration
- Payment Factory States
- Feature/Limit Factories
- Discount Factory
- Subscription Status
- User & OTP
- Plan Controllers
- Visit Management
- Resort & Day Pricing
- Recreational Riding
- Moyasar Normalizers
- Form Requests
- Horse Care
- Center Events
- Horse Services
- Community 25
- Community 26
- Community 27
- Community 28
- Community 29
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 59
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
- Community 77
- Community 78
- Community 79
- Community 81
- Community 82
- Community 83
- Community 84
- Community 86
- Community 87
- Community 88
- Community 89
- Community 91
- Community 92
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
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
- Community 143
- Community 144
- Community 145
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152

## God Nodes (most connected - your core abstractions)
1. `CenterFactory` - 187 edges
2. `Center` - 134 edges
3. `User` - 86 edges
4. `Service` - 81 edges
5. `Payment` - 74 edges
6. `ClientFactory` - 73 edges
7. `PaymentStatus` - 67 edges
8. `Discount` - 66 edges
9. `Plan` - 56 edges
10. `PlanFactory` - 55 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --calls--> `CityFactory`  [EXTRACTED]
  Billing/tests/Unit/EventDispatchTest.php → Support/database/factories/CityFactory.php
- `{closure#1}()` --calls--> `CityFactory`  [EXTRACTED]
  Billing/tests/Unit/ModelStructureTest.php → Support/database/factories/CityFactory.php
- `{closure#1}()` --calls--> `CityFactory`  [EXTRACTED]
  Billing/tests/Unit/SlugTest.php → Support/database/factories/CityFactory.php
- `{closure#1}()` --calls--> `CityFactory`  [EXTRACTED]
  Billing/tests/Unit/SubscriptionEligibilityServiceTest.php → Support/database/factories/CityFactory.php
- `{closure#1}()` --calls--> `CenterFactory`  [EXTRACTED]
  Clients/tests/Feature/RatingControllerTest.php → Centers/database/factories/CenterFactory.php

## Import Cycles
- None detected.

## Communities (173 total, 77 thin omitted)

### Community 0 - "Payment Events & Tests"
Cohesion: 0.05
Nodes (29): {closure#1}(), {closure#2}(), CreatePayment, CreatePurchase, PaymentMethod, CashOnArrival, Manual, Online (+21 more)

### Community 1 - "Payment Gateway Contract"
Cohesion: 0.07
Nodes (18): PaymentGateway, PaymentStatus, Cancelled, Expired, Failed, Pending, Processing, Succeeded (+10 more)

### Community 2 - "Center & Subscription Controllers"
Cohesion: 0.08
Nodes (12): CenterController, CenterResource, CenterWithRoleResource, Center, User, CenterPolicy, CenterAuthService, {closure#1}() (+4 more)

### Community 3 - "Plan Features & Limits"
Cohesion: 0.05
Nodes (7): LimitResource, {closure#3}(), {closure#4}(), {closure#5}(), CouponResource, CityResource, MediaResource

### Community 4 - "Client Profile Tests"
Cohesion: 0.07
Nodes (43): ClientFactory, {closure#10}(), {closure#11}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+35 more)

### Community 5 - "Tamara Gateway Client"
Cohesion: 0.08
Nodes (22): {closure#1}(), TamaraClient, TamaraGateway, PaymentGatewayManager, {closure#10}(), {closure#18}(), {closure#8}(), {closure#1}() (+14 more)

### Community 6 - "Purchase Factory"
Cohesion: 0.09
Nodes (31): {closure#1}(), {closure#2}(), PurchaseFactory, PurchaseStatus, Cancelled, Completed, Confirmed, Pending (+23 more)

### Community 7 - "Plan Factory"
Cohesion: 0.06
Nodes (10): {closure#6}(), {closure#7}(), PlanCreated, {closure#1}(), {closure#2}(), PlanController, AttachFeaturesRequest, UpdatePlanStatusRequest (+2 more)

### Community 8 - "Subscription Events"
Cohesion: 0.09
Nodes (24): SubscriptionCanceled, SubscriptionCreated, SubscriptionRenewed, {closure#2}(), CouponRedeemed, PaymentCancelled, PaymentExpired, PaymentFailed (+16 more)

### Community 9 - "Payment Exceptions & HTTP"
Cohesion: 0.08
Nodes (17): PaymentAuthorizationException, PaymentGatewayException, PaymentInitializationException, PaymentProviderUnavailableException, PaymentProviderValidationException, PaymentStateConflictException, PaymentVerificationException, {closure#2}() (+9 more)

### Community 10 - "Center Auth & Registration"
Cohesion: 0.09
Nodes (9): AuthController, RegisterController, UserResource, CenterRegisterService, AuthController, ProfileController, RatingController, ClientResource (+1 more)

### Community 11 - "Payment Factory States"
Cohesion: 0.11
Nodes (22): PaymentFactory, {closure#1}(), MoyasarClient, MoyasarGateway, {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}() (+14 more)

### Community 12 - "Feature/Limit Factories"
Cohesion: 0.09
Nodes (24): FeatureFactory, LimitFactory, PlanFactory, {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#3}() (+16 more)

### Community 13 - "Discount Factory"
Cohesion: 0.07
Nodes (18): ApplicationMethod, Automatic, Coupon, DiscountScope, All, SpecificItems, DiscountType, Fixed (+10 more)

### Community 14 - "Subscription Status"
Cohesion: 0.09
Nodes (16): SubscriptionStatus, Active, Canceled, Expired, Trialing, SubscriptionIneligibleException, Subscription, BillingIntervalCalculator (+8 more)

### Community 15 - "User & OTP"
Cohesion: 0.08
Nodes (15): OTP, {closure#1}(), {closure#13}(), {closure#16}(), {closure#17}(), {closure#18}(), {closure#19}(), {closure#21}() (+7 more)

### Community 16 - "Plan Controllers"
Cohesion: 0.11
Nodes (8): PlanController, SubscriptionController, PlanCollection, PlanResource, SubscriptionResource, EnsureCenterAccessScope, SetLocaleFromAcceptLanguage, TrackClientSession

### Community 17 - "Visit Management"
Cohesion: 0.15
Nodes (26): CenterFactory, Visit, {closure#1}(), {closure#2}(), {closure#3}(), VisitService, {closure#10}(), {closure#11}() (+18 more)

### Community 18 - "Resort & Day Pricing"
Cohesion: 0.12
Nodes (27): ResortDayPriceFactory, Resort, ResortDayPrice, {closure#1}(), {closure#2}(), {closure#3}(), ResortService, {closure#10}() (+19 more)

### Community 19 - "Recreational Riding"
Cohesion: 0.14
Nodes (28): RecreationalRiding, Schedule, {closure#1}(), {closure#2}(), {closure#3}(), RecreationRidingService, {closure#10}(), {closure#11}() (+20 more)

### Community 20 - "Moyasar Normalizers"
Cohesion: 0.08
Nodes (15): MoyasarSourceNormalizer, MoyasarStatusMapper, TamaraStatusMapper, {closure#1}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#2}() (+7 more)

### Community 21 - "Form Requests"
Cohesion: 0.08
Nodes (7): CancelSubscriptionRequest, UpdateCenterRequest, VerifyLoginOtpRequest, UpdateProfileRequest, VerifyOtpRequest, ServiceTypeTermRequest, UploadMediaRequest

### Community 22 - "Horse Care"
Cohesion: 0.14
Nodes (23): HorseCare, {closure#1}(), {closure#2}(), {closure#3}(), HorseCareService, {closure#10}(), {closure#11}(), {closure#12}() (+15 more)

### Community 23 - "Center Events"
Cohesion: 0.14
Nodes (23): Event, {closure#1}(), {closure#2}(), {closure#3}(), EventService, {closure#10}(), {closure#11}(), {closure#12}() (+15 more)

### Community 24 - "Horse Services"
Cohesion: 0.15
Nodes (24): HorseService, PriceOption, {closure#1}(), {closure#2}(), {closure#3}(), HorseServiceService, {closure#10}(), {closure#11}() (+16 more)

### Community 25 - "Community 25"
Cohesion: 0.08
Nodes (6): HasDiscountable, {closure#5}(), PurchaseItemFactory, {closure#2}(), {closure#3}(), PurchaseItem

### Community 26 - "Community 26"
Cohesion: 0.09
Nodes (19): {closure#1}(), {closure#2}(), {closure#1}(), CenterStatus, INVISIBLE, VISIBLE, CityFactory, {closure#2}() (+11 more)

### Community 27 - "Community 27"
Cohesion: 0.13
Nodes (14): WebhookEventHandler, FakeWebhookEventHandler, MoyasarWebhookEventHandler, TamaraWebhookEventHandler, WebhookEvent, {closure#1}(), WebhookProcessorService, {closure#1}() (+6 more)

### Community 28 - "Community 28"
Cohesion: 0.09
Nodes (16): BillingInterval, Monthly, Yearly, PlanStatus, Active, InActive, SubscriptionAction, Cancel (+8 more)

### Community 29 - "Community 29"
Cohesion: 0.08
Nodes (10): SubscriptionCollection, DiscountCreated, {closure#1}(), {closure#2}(), {closure#2}(), StoreCouponRequest, UpdateCouponStatusRequest, UpdateDiscountStatusRequest (+2 more)

### Community 30 - "Community 30"
Cohesion: 0.11
Nodes (14): SubscriptionFactory, {closure#1}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#1}() (+6 more)

### Community 31 - "Community 31"
Cohesion: 0.11
Nodes (3): Client, ClientAuthService, HasDevices

### Community 32 - "Community 32"
Cohesion: 0.09
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#7}(), {closure#3}(), {closure#7}(), {closure#3}(), {closure#7}()

### Community 33 - "Community 33"
Cohesion: 0.11
Nodes (13): TemporaryUploadFactory, {closure#12}(), {closure#14}(), {closure#15}(), {closure#17}(), {closure#18}(), {closure#20}(), {closure#21}() (+5 more)

### Community 34 - "Community 34"
Cohesion: 0.09
Nodes (5): LimitController, StoreLimitRequest, UpdateLimitRequest, LimitCollection, Limit

### Community 35 - "Community 35"
Cohesion: 0.09
Nodes (12): UserFactory, {closure#5}(), {closure#1}(), {closure#10}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#10}() (+4 more)

### Community 36 - "Community 36"
Cohesion: 0.14
Nodes (6): CouponValidator, {closure#2}(), DiscountEligibilityService, DiscountResolver, Device, DeviceService

### Community 37 - "Community 37"
Cohesion: 0.11
Nodes (4): PlanFeature, PlanLimit, {closure#7}(), CenterUser

### Community 38 - "Community 38"
Cohesion: 0.08
Nodes (3): CenterUserRole, Owner, {closure#9}()

### Community 39 - "Community 39"
Cohesion: 0.19
Nodes (4): DiscountController, {closure#1}(), Discount, DiscountCalculator

### Community 40 - "Community 40"
Cohesion: 0.14
Nodes (6): HorseCareController, HorseCareRequest, HorseCareResource, CenterHorseCareService, {closure#1}(), {closure#2}()

### Community 41 - "Community 41"
Cohesion: 0.13
Nodes (6): FeatureResource, ServiceTypeTermController, ServiceTypeTermResource, CityController, CityController, MediaController

### Community 42 - "Community 42"
Cohesion: 0.09
Nodes (3): RegisterCenterFormRequest, {closure#1}(), {closure#2}()

### Community 43 - "Community 43"
Cohesion: 0.14
Nodes (3): HasCity, City, CityService

### Community 44 - "Community 44"
Cohesion: 0.09
Nodes (9): {closure#1}(), {closure#10}(), {closure#4}(), {closure#5}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+1 more)

### Community 45 - "Community 45"
Cohesion: 0.15
Nodes (6): HorseServiceController, HorseServiceRequest, HorseServiceResource, CenterHorseServiceService, {closure#1}(), {closure#2}()

### Community 46 - "Community 46"
Cohesion: 0.15
Nodes (6): VisitController, VisitRequest, VisitResource, CenterVisitService, {closure#1}(), {closure#2}()

### Community 47 - "Community 47"
Cohesion: 0.11
Nodes (5): FeatureController, StoreFeatureRequest, UpdateFeatureRequest, FeatureCollection, Feature

### Community 48 - "Community 48"
Cohesion: 0.13
Nodes (5): HasSubscriptions, HasDiscounts, IsBuyer, Rateable, Rater

### Community 49 - "Community 49"
Cohesion: 0.13
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}(), {closure#3}(), {closure#4}(), MediaPolicy

### Community 50 - "Community 50"
Cohesion: 0.15
Nodes (13): DiscountRedemptionFactory, baseCreate(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+5 more)

### Community 51 - "Community 51"
Cohesion: 0.15
Nodes (3): EventController, EventRequest, EventResource

### Community 52 - "Community 52"
Cohesion: 0.14
Nodes (10): MediaStatus, Attached, Temporary, InvalidTemporaryMediaException, TemporaryUpload, {closure#1}(), {closure#3}(), {closure#5}() (+2 more)

### Community 53 - "Community 53"
Cohesion: 0.15
Nodes (11): {closure#1}(), {closure#3}(), {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), methodHasAttr() (+3 more)

### Community 54 - "Community 54"
Cohesion: 0.16
Nodes (6): CouponFactory, {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), vCreate()

### Community 55 - "Community 55"
Cohesion: 0.18
Nodes (3): DiscountController, DiscountResource, Coupon

### Community 56 - "Community 56"
Cohesion: 0.13
Nodes (7): AttachLimitsRequest, CreateSubscriptionRequest, StorePlanRequest, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 59 - "Community 59"
Cohesion: 0.17
Nodes (5): DiscountFactory, {closure#3}(), {closure#4}(), {closure#7}(), {closure#8}()

### Community 60 - "Community 60"
Cohesion: 0.16
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#1}(), {closure#2}()

### Community 62 - "Community 62"
Cohesion: 0.16
Nodes (7): DiscountRedeemed, DiscountLimitExceededException, DiscountRedemption, {closure#1}(), {closure#2}(), DiscountUsageService, {closure#1}()

### Community 63 - "Community 63"
Cohesion: 0.15
Nodes (5): CenterRecreationRidingService, {closure#1}(), {closure#2}(), {closure#3}(), {closure#7}()

### Community 64 - "Community 64"
Cohesion: 0.18
Nodes (12): ServiceTypeTerm, {closure#1}(), {closure#2}(), ServiceTypeTermService, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}() (+4 more)

### Community 65 - "Community 65"
Cohesion: 0.15
Nodes (5): DiscountableFactory, HorseCareFactory, HorseServiceFactory, ResortFactory, ScheduleFactory

### Community 66 - "Community 66"
Cohesion: 0.21
Nodes (3): ResortController, ResortRequest, ResortResource

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (5): BillingServiceProvider, CentersServiceProvider, ClientsServiceProvider, PromotionServiceProvider, ServicesServiceProvider

### Community 68 - "Community 68"
Cohesion: 0.12
Nodes (4): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 70 - "Community 70"
Cohesion: 0.16
Nodes (5): CenterEventService, {closure#1}(), {closure#2}(), {closure#3}(), {closure#7}()

### Community 71 - "Community 71"
Cohesion: 0.16
Nodes (5): CenterResortService, {closure#1}(), {closure#2}(), {closure#3}(), {closure#7}()

### Community 72 - "Community 72"
Cohesion: 0.26
Nodes (3): Actionable, ActionActor, ActionLog

### Community 73 - "Community 73"
Cohesion: 0.25
Nodes (3): RecreationRidingController, RecreationRidingRequest, RecreationRidingResource

### Community 74 - "Community 74"
Cohesion: 0.13
Nodes (7): {closure#1}(), {closure#4}(), {closure#5}(), {closure#9}(), ActivationStatus, ACTIVE, INACTIVE

### Community 75 - "Community 75"
Cohesion: 0.22
Nodes (4): DatabaseSeeder, TagSeeder, CitySeeder, DatabaseSeeder

### Community 76 - "Community 76"
Cohesion: 0.22
Nodes (3): MediaService, {closure#6}(), TemporaryMediaService

### Community 78 - "Community 78"
Cohesion: 0.15
Nodes (4): {closure#1}(), Buyer, Purchasable, IsPurchasable

### Community 79 - "Community 79"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 81 - "Community 81"
Cohesion: 0.18
Nodes (3): AttachDiscountablesRequest, Discountable, {closure#1}()

### Community 82 - "Community 82"
Cohesion: 0.15
Nodes (4): RecreationalRidingFactory, ServiceFactory, ActionLogFactory, OTPFactory

### Community 83 - "Community 83"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Centers\\ (+3 more)

### Community 84 - "Community 84"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Clients\\ (+3 more)

### Community 86 - "Community 86"
Cohesion: 0.18
Nodes (9): ServiceTypeTermFactory, ServiceType, Event, HorseCare, HorseService, RecreationRiding, Resort, Visit (+1 more)

### Community 87 - "Community 87"
Cohesion: 0.23
Nodes (4): {closure#1}(), {closure#24}(), {closure#9}(), MediaTestModel

### Community 88 - "Community 88"
Cohesion: 0.17
Nodes (11): autoload, psr-4, description, extra, laravel, providers, name, Modules\\Support\\ (+3 more)

### Community 91 - "Community 91"
Cohesion: 0.18
Nodes (4): {closure#1}(), {closure#10}(), {closure#4}(), {closure#5}()

### Community 92 - "Community 92"
Cohesion: 0.18
Nodes (3): {closure#11}(), {closure#6}(), {closure#7}()

### Community 94 - "Community 94"
Cohesion: 0.42
Nodes (7): {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), methodHasAttr(), publicControllerMethods()

### Community 96 - "Community 96"
Cohesion: 0.28
Nodes (3): CenterUserPermissionRoleSeeder, {closure#1}(), CenterUserPermission

### Community 97 - "Community 97"
Cohesion: 0.22
Nodes (5): {closure#1}(), Gender, FEMALE, MALE, OTHER

### Community 99 - "Community 99"
Cohesion: 0.25
Nodes (4): EventFactory, EventOccurrenceType, General, SpecificDay

### Community 100 - "Community 100"
Cohesion: 0.39
Nodes (7): cCreate(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 101 - "Community 101"
Cohesion: 0.28
Nodes (3): PriceOptionFactory, {closure#1}(), Service

### Community 102 - "Community 102"
Cohesion: 0.22
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#8}()

### Community 103 - "Community 103"
Cohesion: 0.39
Nodes (7): callSlugResolve(), callStoreResolve(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 107 - "Community 107"
Cohesion: 0.48
Nodes (5): {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), rCreate()

### Community 109 - "Community 109"
Cohesion: 0.33
Nodes (4): VisitFactory, VisitEnterType, AllDay, SpecificTime

### Community 118 - "Community 118"
Cohesion: 0.40
Nodes (4): PurchaseSource, Center, Client, System

### Community 145 - "Community 145"
Cohesion: 0.50
Nodes (3): PriceOptionUnit, MINUTE, OPTION

## Knowledge Gaps
- **79 isolated node(s):** `Monthly`, `Yearly`, `Active`, `InActive`, `Subscribe` (+74 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 741 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **77 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Center` connect `Center & Subscription Controllers` to `Community 135`, `Center Auth & Registration`, `Community 142`, `Plan Controllers`, `Community 30`, `Community 32`, `Community 34`, `Community 36`, `Community 38`, `Community 40`, `Community 41`, `Community 43`, `Community 45`, `Community 46`, `Community 48`, `Community 49`, `Community 51`, `Community 55`, `Community 61`, `Community 63`, `Community 64`, `Community 65`, `Community 66`, `Community 70`, `Community 71`, `Community 72`, `Community 73`, `Community 78`, `Community 80`, `Community 82`, `Community 86`, `Community 87`, `Community 89`, `Community 93`, `Community 105`, `Community 112`?**
  _High betweenness centrality (0.084) - this node is a cross-community bridge._
- **Why does `CenterFactory` connect `Visit Management` to `Client Profile Tests`, `Plan Factory`, `Subscription Events`, `Feature/Limit Factories`, `Discount Factory`, `Resort & Day Pricing`, `Recreational Riding`, `Horse Care`, `Center Events`, `Horse Services`, `Community 26`, `Community 30`, `Community 35`, `Community 43`, `Community 44`, `Community 59`, `Community 64`, `Community 65`, `Community 74`, `Community 86`, `Community 89`, `Community 91`, `Community 92`, `Community 93`, `Community 102`, `Community 103`, `Community 107`?**
  _High betweenness centrality (0.080) - this node is a cross-community bridge._
- **Why does `Plan` connect `Plan Factory` to `Center & Subscription Controllers`, `Community 34`, `Community 36`, `Community 37`, `Feature/Limit Factories`, `Subscription Status`, `Plan Controllers`, `Community 80`, `Community 48`, `Community 61`, `Community 56`, `Community 26`, `Community 28`, `Community 93`, `Community 30`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **What connects `Monthly`, `Yearly`, `Active` to the rest of the system?**
  _79 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Payment Events & Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.05191256830601093 - nodes in this community are weakly interconnected._
- **Should `Payment Gateway Contract` be split into smaller, more focused modules?**
  _Cohesion score 0.0711864406779661 - nodes in this community are weakly interconnected._
- **Should `Center & Subscription Controllers` be split into smaller, more focused modules?**
  _Cohesion score 0.07676767676767676 - nodes in this community are weakly interconnected._
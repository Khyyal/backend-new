<?php

namespace Modules\Analytics\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Analytics\Enums\AnalyticsSection;
use Modules\Analytics\Http\Requests\Admin\AnalyticsFilterRequest;
use Modules\Analytics\Models\DailyPlatformStat;
use Modules\Analytics\Services\BookingsAnalytics;
use Modules\Analytics\Services\CentersAnalytics;
use Modules\Analytics\Services\ClientsAnalytics;
use Modules\Analytics\Services\EngagementAnalytics;
use Modules\Analytics\Services\OverviewAnalytics;
use Modules\Analytics\Services\PaymentsAnalytics;
use Modules\Analytics\Services\PromotionsAnalytics;
use Modules\Analytics\Services\RevenueAnalytics;
use Modules\Analytics\Services\SubscriptionsAnalytics;
use Modules\Analytics\Support\AnalyticsPeriod;
use Modules\Analytics\Support\RollupReader;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Admin / Analytics', 'Platform KPIs read from nightly rollups (built at 00:30 UTC; the last 35 days are restated each night). Requires `analytics.view`. Every KPI is `{value, previous, growth}`; `growth` is a percent, null without a comparison or when `previous` is 0. Money is in halalas, rates are percentages (0-100). Client and subscription metrics are always platform-wide.')]
class AnalyticsController extends Controller
{
    public function __construct(private readonly RollupReader $reader) {}

    #[Endpoint('Overview analytics', 'Headline tiles and the revenue series.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['gmv' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'platform_net_revenue' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'take_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'purchases_count' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'average_order_value' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'new_buyers' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'active_buyers_30d' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'active_centers_30d' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'cancellation_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'payment_success_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'gmv' => 12, 'purchases_count' => 12, 'net_fees' => 12, 'platform_sales' => 12, 'platform_net_revenue' => 12, 'refunds' => 12, 'discounts' => 12]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The overview dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.gmv', 'object', 'Gross merchandise value of counted center purchases (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.platform_net_revenue', 'object', 'What the platform keeps after subsidies and provider fees (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.take_rate', 'object', 'Net fees as a percent of GMV. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.purchases_count', 'object', 'Counted (confirmed/completed) purchases. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.average_order_value', 'object', 'GMV / purchases (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.new_buyers', 'object', 'Clients with their first purchase in the range. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.active_buyers_30d', 'object', 'Clients with a purchase in the 30 days to the range end. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.active_centers_30d', 'object', 'Centers with a sale in the 30 days to the range end. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.cancellation_rate', 'object', 'Cancelled reservations as a percent of scheduled ones. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.payment_success_rate', 'object', 'Succeeded online payments as a percent of attempts. `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function overview(AnalyticsFilterRequest $request, OverviewAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    #[Endpoint('Revenue analytics', 'GMV, net fees and take rate, platform sales, subsidies, provider fees, platform net revenue, discounts by funder and refunds; GMV by channel, service type and city.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['gmv' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'purchases_count' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'average_order_value' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'net_fees' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'take_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'platform_sales' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'platform_subsidies' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'provider_fees' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'platform_net_revenue' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'discounts_platform_funded' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'discounts_center_funded' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'refunds' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'refund_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'gmv' => 12, 'purchases_count' => 12, 'net_fees' => 12, 'platform_sales' => 12, 'platform_net_revenue' => 12, 'refunds' => 12, 'discounts' => 12]], 'by_channel' => [['key' => 'khyyal_app', 'purchases_count' => 42, 'gmv' => 1260000, 'share' => 62.5]], 'by_service_type' => [['key' => 'recreation_riding', 'purchases_count' => 30, 'gmv' => 900000, 'share' => 44.6]], 'by_city' => [['city_id' => 1, 'name' => 'Riyadh', 'purchases_count' => 50, 'gmv' => 1500000, 'share' => 74.4]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The revenue dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.gmv', 'object', 'Gross merchandise value of counted center purchases (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.purchases_count', 'object', 'Counted (confirmed/completed) purchases. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.average_order_value', 'object', 'GMV / purchases (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.net_fees', 'object', 'Platform fees net of reversals (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.take_rate', 'object', 'Net fees as a percent of GMV. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.platform_sales', 'object', 'Platform sales such as subscriptions and memberships (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.platform_subsidies', 'object', 'Platform-funded discounts (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.provider_fees', 'object', 'Payment provider fees (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.platform_net_revenue', 'object', 'What the platform keeps after subsidies and provider fees (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.discounts_platform_funded', 'object', 'Discounts funded by the platform (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.discounts_center_funded', 'object', 'Discounts funded by centers (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.refunds', 'object', 'Refunded amount (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.refund_rate', 'object', 'Refunds as a percent of GMV. `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('data.by_channel', 'object[]', 'GMV by sales channel: `key`, `purchases_count`, `gmv`, `share` (percent).')]
    #[ResponseField('data.by_service_type', 'object[]', 'Breakdown by service type: `key`, `purchases_count`, `gmv` (and `share` on revenue).')]
    #[ResponseField('data.by_city', 'object[]', 'GMV by center city: `city_id`, `name`, `purchases_count`, `gmv`, `share`.')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function revenue(AnalyticsFilterRequest $request, RevenueAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    #[Endpoint('Bookings analytics', 'Bookings and tickets (by creation date); reservations scheduled in the range (by service date) by outcome, with cancellation and no-show rates; purchases by service type.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['bookings_made' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'tickets_booked' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'reservations_scheduled' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'reservations_completed' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'reservations_no_show' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'reservations_cancelled' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'cancellations_by_client' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'cancellation_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'client_cancellation_share' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'no_show_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'bookings_made' => 12, 'tickets_booked' => 12, 'reservations_scheduled' => 12, 'reservations_completed' => 12, 'reservations_no_show' => 12, 'reservations_cancelled' => 12, 'cancellations_by_client' => 12, 'cancellation_rate' => 12]], 'by_service_type' => [['key' => 'recreation_riding', 'purchases_count' => 30, 'gmv' => 900000, 'share' => 44.6]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The bookings dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.bookings_made', 'object', 'Bookings created in the range. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.tickets_booked', 'object', 'Tickets/people booked in the range. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.reservations_scheduled', 'object', 'Reservations whose service date is in the range. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.reservations_completed', 'object', 'Of those, completed. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.reservations_no_show', 'object', 'Of those, no-shows. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.reservations_cancelled', 'object', 'Of those, cancelled. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.cancellations_by_client', 'object', 'Cancellations made by clients. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.cancellation_rate', 'object', 'Cancelled reservations as a percent of scheduled ones. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.client_cancellation_share', 'object', 'Client cancellations as a percent of all cancellations. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.no_show_rate', 'object', 'No-shows as a percent of scheduled reservations. `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('data.by_service_type', 'object[]', 'Breakdown by service type: `key`, `purchases_count`, `gmv` (and `share` on revenue).')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function bookings(AnalyticsFilterRequest $request, BookingsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    #[Endpoint('Payments analytics', 'Online payment attempts, successes, failures and success rate, purchase conversion and provider fees, with the mix by method, gateway and type.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['payments_attempted' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'payments_succeeded' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'payments_failed' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'payment_success_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'purchases_created' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'purchase_conversion_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'provider_fees' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'payments_attempted' => 12, 'payments_succeeded' => 12, 'payments_failed' => 12, 'payment_success_rate' => 12, 'purchase_conversion_rate' => 12]], 'by_method' => [['key' => 'online', 'attempts' => 60, 'succeeded' => 54, 'success_rate' => 90.0, 'amount' => 1600000, 'share' => 80.0]], 'by_company' => [['key' => 'moyasar', 'attempts' => 50, 'succeeded' => 46, 'success_rate' => 92.0, 'amount' => 1400000, 'share' => 70.0]], 'by_type' => [['key' => 'creditcard', 'attempts' => 40, 'succeeded' => 37, 'success_rate' => 92.5, 'amount' => 1100000, 'share' => 55.0]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The payments dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.payments_attempted', 'object', 'Online payment attempts. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.payments_succeeded', 'object', 'Succeeded payments. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.payments_failed', 'object', 'Failed payments. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.payment_success_rate', 'object', 'Succeeded online payments as a percent of attempts. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.purchases_created', 'object', 'Purchases created. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.purchase_conversion_rate', 'object', 'Counted purchases as a percent of created ones. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.provider_fees', 'object', 'Payment provider fees (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('data.by_method', 'object[]', 'Payment mix by method: `key`, `attempts`, `succeeded`, `success_rate`, `amount`, `share`.')]
    #[ResponseField('data.by_company', 'object[]', 'Payment mix by gateway (same fields).')]
    #[ResponseField('data.by_type', 'object[]', 'Payment mix by payment type (card, Apple Pay…; same fields).')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function payments(AnalyticsFilterRequest $request, PaymentsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    #[Endpoint('Clients analytics', 'Sign-ups, new buyers and conversion, active buyers over 1/7/30 days at the range end, monthly repeat buyer rate and the first-purchase cohort retention matrix.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['signups' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'new_buyers' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'signup_conversion_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'active_buyers_1d' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'active_buyers_7d' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'active_buyers_30d' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'signups' => 12, 'new_buyers' => 12, 'active_buyers_7d' => 12, 'active_buyers_30d' => 12]], 'monthly' => [['month' => '2026-10-01', 'signups' => 120, 'buyers' => 80, 'new_buyers' => 35, 'repeat_buyers' => 20, 'repeat_rate' => 25.0]], 'cohorts' => [['cohort_month' => '2026-05-01', 'cohort_size' => 40, 'retention' => [['months_since' => 0, 'active_buyers' => 40, 'rate' => 100.0]]]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The clients dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.signups', 'object', 'New client accounts. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.new_buyers', 'object', 'Clients with their first purchase in the range. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.signup_conversion_rate', 'object', 'New buyers as a percent of sign-ups. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.active_buyers_1d', 'object', 'Buyers on the last day. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.active_buyers_7d', 'object', 'Buyers in the last 7 days. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.active_buyers_30d', 'object', 'Clients with a purchase in the 30 days to the range end. `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('data.monthly', 'object[]', 'Per month: `month`, `signups`, `buyers`, `new_buyers`, `repeat_buyers`, `repeat_rate`.')]
    #[ResponseField('data.cohorts', 'object[]', 'First-purchase cohorts (12 months): `cohort_month`, `cohort_size`, `retention` [{`months_since` 0-12, `active_buyers`, `rate`}].')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function clients(AnalyticsFilterRequest $request, ClientsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    #[Endpoint('Centers analytics', 'Center counts, states and payout liability (latest snapshot), a leaderboard and the at-risk list.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['centers_total' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'new_centers' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'active_centers_30d' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'states' => ['active' => 12, 'pending' => 12, 'blocked' => 12], 'payout_liability' => ['unpaid_balance' => 12, 'on_hold' => 12, 'pending_payouts' => 12, 'as_of' => '2026-11-07'], 'series' => [['date' => '2026-10-01', 'new_centers' => 12, 'centers_total' => 12, 'active_centers_30d' => 12]], 'leaderboard' => [['center_id' => 8, 'name' => 'Al Fursan Stables', 'state' => 'active', 'gmv' => ['value' => 600000, 'previous' => 500000, 'growth' => 20.0], 'purchases_count' => 20, 'net_fees' => 30000, 'take_rate' => 5.0, 'cancellation_rate' => 4.0, 'ratings_count' => 18, 'average_rating' => 4.6]], 'at_risk' => [['center_id' => 9, 'name' => 'Desert Riders', 'reason' => 'gmv_drop', 'gmv' => 100000, 'previous_gmv' => 400000, 'last_sale_on' => '2026-11-02']]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The centers dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.centers_total', 'object', 'Centers at the range end. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.new_centers', 'object', 'Centers created in the range. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.active_centers_30d', 'object', 'Centers with a sale in the 30 days to the range end. `{value, previous, growth}`.')]
    #[ResponseField('data.states', 'object', 'Centers by account state (latest snapshot): `active`, `pending`, `blocked`.')]
    #[ResponseField('data.payout_liability', 'object', 'Owed to centers (latest snapshot, halalas): `unpaid_balance`, `on_hold`, `pending_payouts`, `as_of`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('data.leaderboard', 'object[]', 'Centers ranked by `sort`: `center_id`, `name`, `state`, `gmv` (KPI), `purchases_count`, `net_fees`, `take_rate`, `cancellation_rate`, `ratings_count`, `average_rating`.')]
    #[ResponseField('data.at_risk', 'object[]', 'Active centers whose GMV dropped by the configured percent (`reason: gmv_drop`) or stopped selling (`idle`): `center_id`, `name`, `reason`, `gmv`, `previous_gmv`, `last_sale_on`.')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function centers(AnalyticsFilterRequest $request, CentersAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report(
            $period,
            $request->validated('sort', 'gmv'),
            (int) $request->validated('limit', 10),
        ));
    }

    #[Endpoint('Promotions analytics', 'Redemptions, discount given, promoted GMV and its share, redemption rate, top promotions and coupons.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['redemptions' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'discount_given' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'promoted_gmv' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'promoted_gmv_share' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'redemption_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'redemptions' => 12, 'discount_given' => 12]], 'top_promotions' => [['id' => 12, 'name' => '15% off rides', 'redemptions' => 30, 'discount_given' => 135000, 'promoted_gmv' => 765000]], 'top_coupons' => [['id' => 41, 'name' => 'SUMMER25', 'redemptions' => 12, 'discount_given' => 54000, 'promoted_gmv' => 306000]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The promotions dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.redemptions', 'object', 'Applied redemptions. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.discount_given', 'object', 'Discount given (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.promoted_gmv', 'object', 'GMV of purchases with a promotion (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.promoted_gmv_share', 'object', 'Promoted GMV as a percent of GMV. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.redemption_rate', 'object', 'Purchases with a promotion as a percent of purchases. `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('data.top_promotions', 'object[]', 'Top promotions: `id`, `name`, `redemptions`, `discount_given`, `promoted_gmv`.')]
    #[ResponseField('data.top_coupons', 'object[]', 'Top coupons (same fields; `name` is the code).')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function promotions(AnalyticsFilterRequest $request, PromotionsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    #[Endpoint('Subscriptions analytics', 'Paying and trialing subscriptions and MRR at the range end, new and cancelled subscriptions and churn.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['subscriptions_active' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'subscriptions_trialing' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'subscriptions_new' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'subscriptions_cancelled' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'mrr' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'churn_rate' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'subscriptions_new' => 12, 'subscriptions_cancelled' => 12, 'subscriptions_active' => 12, 'subscriptions_trialing' => 12, 'mrr' => 12]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The subscriptions dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.subscriptions_active', 'object', 'Paying subscriptions at the range end. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.subscriptions_trialing', 'object', 'Trialing subscriptions at the range end. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.subscriptions_new', 'object', 'New subscriptions. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.subscriptions_cancelled', 'object', 'Cancelled subscriptions. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.mrr', 'object', 'Monthly recurring revenue at the range end (halalas). `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.churn_rate', 'object', 'Cancelled as a percent of active at the start. `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function subscriptions(AnalyticsFilterRequest $request, SubscriptionsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    #[Endpoint('Engagement analytics', 'Client engagement counted live from the apps: center, service, map and WhatsApp clicks, the series and the most engaged centers and services.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-10-10', 'to' => '2026-11-08', 'previous' => ['from' => '2026-09-10', 'to' => '2026-10-09'], 'granularity' => 'month'], 'kpis' => ['center_clicks' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'service_clicks' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'map_clicks' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0], 'whatsapp_clicks' => ['value' => 1200, 'previous' => 1000, 'growth' => 20.0]], 'series' => [['date' => '2026-10-01', 'center_clicks' => 12, 'service_clicks' => 12, 'map_clicks' => 12, 'whatsapp_clicks' => 12]], 'top_centers' => [['center_id' => 8, 'name' => 'Al Fursan Stables', 'total_clicks' => 340]], 'top_services' => [['service_id' => 31, 'name' => 'Beginner ride', 'center_id' => 8, 'center_name' => 'Al Fursan Stables', 'service_clicks' => 120]]], 'meta' => ['data_as_of' => ['date' => '2026-11-08', 'built_at' => '2026-11-09T00:30:12+00:00']]], 200, 'The engagement dashboard.')]
    #[ResponseField('data.period', 'object', 'Range: `from`, `to`, the comparison `previous` range (null with `compare=none`) and the `granularity` used.')]
    #[ResponseField('data.kpis.center_clicks', 'object', 'Center page opens. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.service_clicks', 'object', 'Service page opens. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.map_clicks', 'object', 'Location (map) opens. `{value, previous, growth}`.')]
    #[ResponseField('data.kpis.whatsapp_clicks', 'object', 'WhatsApp opens. `{value, previous, growth}`.')]
    #[ResponseField('data.series', 'object[]', 'One entry per `granularity` bucket (`date` = bucket start) with the series metrics.')]
    #[ResponseField('data.top_centers', 'object[]', 'Most engaged centers: `center_id`, `name`, `total_clicks`.')]
    #[ResponseField('data.top_services', 'object[]', 'Most clicked services: `service_id`, `name`, `center_id`, `center_name`, `service_clicks`.')]
    #[ResponseField('meta.data_as_of', 'object', 'Latest built rollup: `date` (`Y-m-d`) and `built_at` (ISO-8601); null before the first build.', nullable: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function engagement(AnalyticsFilterRequest $request, EngagementAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report(
            $period,
            (int) $request->validated('limit', 10),
        ));
    }

    #[Endpoint('Export a section as CSV', 'One row per series bucket of the chosen `section`, with the same filters.')]
    #[ApiResponse(status: 200, description: '`text/csv` attachment, one row per series bucket (columns: `date` plus the section series metrics).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`.')]
    #[ApiResponse(['message' => 'The to field must be a date before today.', 'errors' => ['to' => ['The to field must be a date before today.']]], 422, 'Invalid filters.')]
    public function export(AnalyticsFilterRequest $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', DailyPlatformStat::class);

        $section = AnalyticsSection::from($request->validated('section', AnalyticsSection::Overview->value));
        $period = AnalyticsPeriod::fromFilters($request->validated());

        $service = app(match ($section) {
            AnalyticsSection::Overview, AnalyticsSection::Revenue => RevenueAnalytics::class,
            AnalyticsSection::Bookings => BookingsAnalytics::class,
            AnalyticsSection::Payments => PaymentsAnalytics::class,
            AnalyticsSection::Clients => ClientsAnalytics::class,
            AnalyticsSection::Centers => CentersAnalytics::class,
            AnalyticsSection::Promotions => PromotionsAnalytics::class,
            AnalyticsSection::Subscriptions => SubscriptionsAnalytics::class,
            AnalyticsSection::Engagement => EngagementAnalytics::class,
        });

        $rows = $service->series($period);
        $filename = sprintf('analytics-%s-%s-%s.csv', $section->value, $period->from->toDateString(), $period->to->toDateString());

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($rows !== []) {
                fputcsv($out, array_keys($rows[0]));
            }

            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  callable(AnalyticsPeriod): array<string, mixed>  $report
     */
    private function respond(AnalyticsFilterRequest $request, callable $report): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', DailyPlatformStat::class);

        return response()->json([
            'data' => $report(AnalyticsPeriod::fromFilters($request->validated())),
            'meta' => ['data_as_of' => $this->reader->dataAsOf()],
        ]);
    }
}

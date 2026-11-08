<?php

namespace Modules\Analytics\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group(name: 'Admin / Analytics', description: <<<'MD'
    Platform KPIs read from nightly rollups (built at 00:30 UTC; the last 35 days are restated each night). Requires `analytics.view`.

    **Query** (all optional, on every endpoint):
    - `from`, `to`: `Y-m-d`, inclusive, at most yesterday; defaults to the last 30 complete days; at most 366 days.
    - `compare`: `prior_period` (default, same length right before), `prior_year` or `none`.
    - `granularity`: `day`, `week` (starting Sunday) or `month`; defaults from the range length.
    - `center_id`, `city_id`: slice center-level metrics. Client and subscription metrics are always platform-wide.

    Every KPI is `{value, previous, growth}`; `growth` is a percent, `null` without a comparison or when `previous` is 0.
    Money is in halalas, rates are percentages (0-100). `meta.data_as_of` gives the latest built day.
    MD)]
class AnalyticsController extends Controller
{
    public function __construct(private readonly RollupReader $reader) {}

    /**
     * Overview.
     *
     * Headline tiles: GMV, platform net revenue, take rate, purchases, average
     * order value, new buyers, active buyers and centers (trailing 30 days at
     * the range end), cancellation rate and payment success rate, plus the
     * revenue series.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series}, meta: {data_as_of}}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function overview(AnalyticsFilterRequest $request, OverviewAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    /**
     * Revenue.
     *
     * GMV (counted center purchases), net platform fees and take rate,
     * platform sales (subscriptions, memberships), platform-funded subsidies,
     * provider fees, platform net revenue, discounts by funder and refunds;
     * with GMV split by channel, service type and city.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series, by_channel, by_service_type, by_city}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function revenue(AnalyticsFilterRequest $request, RevenueAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    /**
     * Bookings.
     *
     * Bookings made and tickets booked (by creation date); reservations
     * scheduled in the range (by service date) by outcome, with cancellation,
     * client-cancellation and no-show rates, and purchases by service type.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series, by_service_type}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function bookings(AnalyticsFilterRequest $request, BookingsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    /**
     * Payments.
     *
     * Online payment attempts, successes and failures, success rate, purchase
     * conversion (counted / created) and provider fees, with the mix by
     * method, gateway and payment type.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series, by_method, by_company, by_type}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function payments(AnalyticsFilterRequest $request, PaymentsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    /**
     * Clients.
     *
     * Sign-ups, new buyers and sign-up conversion, active buyers over 1/7/30
     * days at the range end, monthly repeat buyer rate and the first-purchase
     * cohort retention matrix (12 cohorts, months 0-12).
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series, monthly, cohorts}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function clients(AnalyticsFilterRequest $request, ClientsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    /**
     * Centers.
     *
     * Center counts, center states and payout liability (latest snapshot), a
     * leaderboard (`sort` = `gmv` (default), `purchases_count` or `net_fee`;
     * `limit` 1-50, default 10) and the at-risk list: active centers whose GMV
     * dropped by the configured percent (`gmv_drop`) or that stopped selling
     * (`idle`).
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, states, payout_liability, series, leaderboard, at_risk}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function centers(AnalyticsFilterRequest $request, CentersAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report(
            $period,
            $request->validated('sort', 'gmv'),
            (int) $request->validated('limit', 10),
        ));
    }

    /**
     * Promotions.
     *
     * Applied redemptions, discount given, GMV of promoted purchases and its
     * share, redemption rate, and the top promotions and coupons.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series, top_promotions, top_coupons}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function promotions(AnalyticsFilterRequest $request, PromotionsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    /**
     * Subscriptions.
     *
     * Paying and trialing subscriptions and MRR at the range end, new and
     * cancelled subscriptions and churn rate.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function subscriptions(AnalyticsFilterRequest $request, SubscriptionsAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report($period));
    }

    /**
     * Engagement.
     *
     * Client engagement with centers, counted live from the apps: center
     * clicks, service clicks, map (location) clicks and WhatsApp clicks, the
     * series, and the most engaged centers and services (`limit` 1-50,
     * default 10). Honors `center_id` and `city_id`.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis, series, top_centers, top_services}, meta}`.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
    public function engagement(AnalyticsFilterRequest $request, EngagementAnalytics $analytics): JsonResponse
    {
        return $this->respond($request, fn (AnalyticsPeriod $period): array => $analytics->report(
            $period,
            (int) $request->validated('limit', 10),
        ));
    }

    /**
     * Export a section's series as CSV.
     *
     * `section` is `overview` (default), `revenue`, `bookings`, `payments`,
     * `clients`, `centers`, `promotions`, `subscriptions` or `engagement`; one row per
     * series bucket.
     */
    #[ApiResponse(status: 200, description: '`text/csv` download.')]
    #[ApiResponse(status: 403, description: 'Missing `analytics.view`.')]
    #[ApiResponse(status: 422, description: 'Invalid filters.')]
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

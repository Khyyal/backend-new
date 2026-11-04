<?php

namespace Modules\Centers\Http\Controllers\Center;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Resources\Center\CenterAnalyticsResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAnalytics;

#[Group(name: 'Center / Analytics', description: 'Dashboard KPIs and rankings. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class AnalyticsController extends Controller
{
    public function __construct(private readonly CenterAnalytics $analytics) {}

    /**
     * Show the center's analytics.
     *
     * KPIs over the range, each with its value for the same-length span right
     * before it and the `growth` between them in percent (`null` when the
     * previous value is 0): purchases count, total revenue (minor units), new
     * clients (first purchase at the center in the range) and repeat client
     * rate (percent of the range's buyers with two or more purchases at the
     * center up to the range end). Only confirmed and completed purchases count.
     *
     * `top_services` ranks services by purchases; `top_trainers` ranks trainers
     * by non-cancelled reservations made in the range.
     *
     * **Query** (all optional):
     * - `from`, `to`    `Y-m-d`, inclusive range; defaults to the last 30 days.
     * - `limit`         size of each ranking, 1-20, default 5.
     */
    #[Response(status: 200, description: 'The `CenterAnalyticsResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s analytics.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    public function __invoke(Request $request, Center $center): CenterAnalyticsResource
    {
        Gate::forUser($request->user())->authorize('viewAnalytics', $center);

        $validated = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->subDays(29);

        if ($from->greaterThan($to)) {
            $from = $to;
        }

        return CenterAnalyticsResource::make(
            $this->analytics->overview($center, $from, $to, (int) ($validated['limit'] ?? 5)),
        );
    }
}

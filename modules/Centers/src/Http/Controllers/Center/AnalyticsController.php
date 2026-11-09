<?php

namespace Modules\Centers\Http\Controllers\Center;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Resources\Center\CenterAnalyticsResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterAnalytics;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Analytics', 'Dashboard KPIs and rankings. Requires the center permission `analytics.view`.')]
class AnalyticsController extends Controller
{
    public function __construct(private readonly CenterAnalytics $analytics) {}

    #[Endpoint('Show the center analytics', 'KPIs over the range, each with its value for the same-length span right before it and the growth between them. Only confirmed and completed purchases count. Defaults to the last 30 days.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('from', 'string', 'Range start, `Y-m-d`. Default 29 days before `to`.', required: false, example: '2026-05-01')]
    #[QueryParam('to', 'string', 'Range end, `Y-m-d`, not before `from`. Default today.', required: false, example: '2026-05-30')]
    #[QueryParam('limit', 'integer', 'Size of each ranking, 1-20. Default 5.', required: false, example: 5)]
    #[ResponseFromApiResource(CenterAnalyticsResource::class, Center::class, status: 200, description: 'The analytics.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `analytics.view`. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The limit field must not be greater than 20.', 'errors' => ['limit' => ['The limit field must not be greater than 20.']]], 422, 'Invalid filters.')]
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

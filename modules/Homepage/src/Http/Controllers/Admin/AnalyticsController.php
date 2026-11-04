<?php

namespace Modules\Homepage\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Homepage\Http\Requests\Admin\HomeContentAnalyticsRequest;
use Modules\Homepage\Models\HomeSection;
use Modules\Homepage\Services\HomeContentAnalytics;

#[Group(name: 'Admin / Home page', description: 'Sections, curated items and banners of the client home page. Requires `homepage.manage`.')]
class AnalyticsController extends Controller
{
    /**
     * Home content dashboard.
     *
     * Impressions, clicks and click-through rate (percent) of home content,
     * as reported by the apps through `POST /clients/home/events`, with a
     * zero-filled daily series and the top content by clicks. Defaults to the
     * last 30 days. `compare` is `prior_period` (default), `prior_year` or
     * `none`; `entity_type` restricts to `home_section`, `banner`, `center`,
     * `service`, `promotion` or `campaign`; `limit` (1-50, default 10) sizes
     * the top list.
     */
    #[ApiResponse(status: 200, description: '`{data: {period, kpis: {impressions, clicks, click_through_rate}, series, top_content}}`.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function homeContent(HomeContentAnalyticsRequest $request, HomeContentAnalytics $analytics): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', HomeSection::class);

        return response()->json(['data' => $analytics->dashboard($request->validated())]);
    }
}

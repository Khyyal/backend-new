<?php

namespace Modules\Homepage\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Homepage\Http\Requests\Admin\HomeContentAnalyticsRequest;
use Modules\Homepage\Models\HomeSection;
use Modules\Homepage\Services\HomeContentAnalytics;
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Home page', 'Sections, curated items and banners of the client home page. Requires `homepage.manage`.')]
class AnalyticsController extends Controller
{
    #[Endpoint('Show the home content dashboard', 'Impressions, clicks and click-through rate (percent) reported by the apps, with a zero-filled daily series and the top content by clicks. Defaults to the last 30 days.')]
    #[ApiResponse(['data' => ['period' => ['from' => '2026-05-01', 'to' => '2026-05-30', 'prior' => ['from' => '2026-04-01', 'to' => '2026-04-30']], 'kpis' => ['impressions' => ['value' => 12000, 'prior' => 10000, 'change_percent' => 20.0], 'clicks' => ['value' => 600, 'prior' => 450, 'change_percent' => 33.33], 'click_through_rate' => ['value' => 5.0, 'prior' => 4.5, 'change_percent' => 11.11]], 'series' => [['date' => '2026-05-01', 'impressions' => 400, 'clicks' => 20]], 'top_content' => [['entity_type' => 'banner', 'entity_id' => 5, 'label' => 'Summer rides', 'impressions' => 3000, 'clicks' => 210, 'click_through_rate' => 7.0]]]], 200, 'The dashboard.')]
    #[ResponseField('data.period', 'object', 'Range `from`/`to` and the comparison `prior` range (null with `compare=none`).')]
    #[ResponseField('data.kpis.impressions.value', 'number', 'Value in the range.', example: 12000)]
    #[ResponseField('data.kpis.impressions.prior', 'number', 'Value in the comparison period; null with `compare=none`.', nullable: true, example: 10000)]
    #[ResponseField('data.kpis.impressions.change_percent', 'number', 'Change in percent; null when there is no prior value.', nullable: true, example: 20.0)]
    #[ResponseField('data.kpis.clicks.value', 'number', 'Value in the range.', example: 600)]
    #[ResponseField('data.kpis.clicks.prior', 'number', 'Value in the comparison period; null with `compare=none`.', nullable: true, example: 450)]
    #[ResponseField('data.kpis.clicks.change_percent', 'number', 'Change in percent; null when there is no prior value.', nullable: true, example: 33.33)]
    #[ResponseField('data.kpis.click_through_rate.value', 'number', 'Value in the range.', example: 5.0)]
    #[ResponseField('data.kpis.click_through_rate.prior', 'number', 'Value in the comparison period; null with `compare=none`.', nullable: true, example: 4.5)]
    #[ResponseField('data.kpis.click_through_rate.change_percent', 'number', 'Change in percent; null when there is no prior value.', nullable: true, example: 11.11)]
    #[ResponseField('data.series', 'object[]', 'One entry per day: `date`, `impressions`, `clicks`.')]
    #[ResponseField('data.top_content', 'object[]', 'Top content by clicks: `entity_type`, `entity_id`, `label`, `impressions`, `clicks`, `click_through_rate`.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(['message' => 'The to date field is required when from date is present.', 'errors' => ['to_date' => ['The to date field is required when from date is present.']]], 422, 'Validation failed.')]
    public function homeContent(HomeContentAnalyticsRequest $request, HomeContentAnalytics $analytics): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', HomeSection::class);

        return response()->json(['data' => $analytics->dashboard($request->validated())]);
    }
}

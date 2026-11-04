<?php

namespace Modules\Homepage\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Homepage\Http\Requests\Client\HomeEventsRequest;
use Modules\Homepage\Http\Requests\Client\HomeRequest;
use Modules\Homepage\Services\ContentEventRecorder;
use Modules\Homepage\Services\HomeContentService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Client / Home', description: 'The curated home page. Public, no authentication required. Texts and banner images follow `Accept-Language`.')]
class HomeController extends Controller
{
    public function __construct(
        private readonly HomeContentService $content,
        private readonly ContentEventRecorder $recorder,
    ) {}

    /**
     * Home page.
     *
     * Published sections whose schedule is running, in order, each with its
     * first items and `has_more`. Sections with nothing to show are left out.
     * Item shapes by section type: `banner_carousel` banners
     * (`{id, title, subtitle, cta_label, image, action}`), `curated_centers`
     * and `top_centers` centers, `curated_services` services (with
     * `start_price`/`display_price` in minor units), `curated_promotions`
     * offers, `curated_campaigns` campaigns. `lat`/`lng` (optional, together)
     * order `top_centers` by distance and apply section radiuses.
     */
    #[ApiResponse(status: 200, description: '`{data: [{id, key, type, title, subtitle, items, has_more}]}`.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function index(HomeRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->content->compose($request->latitude(), $request->longitude()),
        ]);
    }

    /**
     * Home section.
     *
     * Paginated items of one visible section (`per_page` defaults to the
     * section limit). 404 when the section does not exist, is not published
     * or is outside its schedule.
     */
    #[ApiResponse(status: 200, description: '`{data: {id, key, type, title, subtitle, items, has_more}, meta}`.')]
    #[ApiResponse(status: 404, description: 'No visible section with that key.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function section(HomeRequest $request, string $key): JsonResponse
    {
        $result = $this->content->section(
            $key,
            $request->latitude(),
            $request->longitude(),
            (int) $request->validated('page', 1),
            $request->validated('per_page') !== null ? (int) $request->validated('per_page') : null,
        );

        abort_if($result === null, 404);

        return response()->json($result);
    }

    /**
     * Report home content events.
     *
     * Batched impressions and clicks (max 50 per request) of sections,
     * banners and the items shown on the home page. Aggregated per day;
     * nothing identifying is stored. Events for unknown entities are ignored.
     * Throttled to 60 requests per minute.
     */
    #[ApiResponse(status: 202, description: '`{data: {accepted: int}}`, the number of events counted.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    #[ApiResponse(status: 429, description: 'Too many requests.')]
    public function events(HomeEventsRequest $request): JsonResponse
    {
        $accepted = $this->recorder->record($request->validated('events'));

        return response()->json(['data' => ['accepted' => $accepted]], ResponseAlias::HTTP_ACCEPTED);
    }
}

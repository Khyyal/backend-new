<?php

namespace Modules\Centers\Http\Controllers\Client;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Client\ListCentersRequest;
use Modules\Centers\Http\Requests\Client\ShowCenterRequest;
use Modules\Centers\Http\Resources\Client\CenterDetailsResource;
use Modules\Centers\Http\Resources\Client\CenterResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterDetailsService;
use Modules\Centers\Services\CenterSearchService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Centers', 'Browse and search centers. Public: no token needed.')]
class CenterController extends Controller
{
    public function __construct(
        private readonly CenterSearchService $service,
        private readonly CenterDetailsService $detailsService,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Search centers', 'Only visible centers, 15 per page. Ordered by popularity (default) or distance.')]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'Paginated centers.', paginate: 15, with: ['city', 'tags'])]
    #[ApiResponse(['message' => 'The lat field is required when order is nearest.', 'errors' => ['lat' => ['The lat field is required when order is nearest.']]], 422, 'Validation failed (e.g. `order=nearest` without `lat`/`lng`).')]
    public function index(ListCentersRequest $request): AnonymousResourceCollection
    {
        return CenterResource::collection($this->service->search($request->validated()));
    }

    #[Unauthenticated]
    #[Endpoint('Show a center', 'Looked up by slug; only visible, active centers. Includes images, tags and an overview of active services.')]
    #[UrlParam('slug', 'string', 'Center slug.', example: 'al-fursan-stables')]
    #[ResponseFromApiResource(CenterDetailsResource::class, Center::class, status: 200, description: 'The center.', with: ['city', 'tags'])]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Center not found or not visible.')]
    #[ApiResponse(['message' => 'The lng field is required when lat is present.', 'errors' => ['lng' => ['The lng field is required when lat is present.']]], 422, 'Validation failed.')]
    public function show(ShowCenterRequest $request, string $slug): CenterDetailsResource
    {
        $center = $this->detailsService->findBySlug(
            $slug,
            $request->filled('lat') ? (float) $request->input('lat') : null,
            $request->filled('lng') ? (float) $request->input('lng') : null,
        );

        return new CenterDetailsResource($center);
    }
}

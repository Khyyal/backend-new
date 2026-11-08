<?php

namespace Modules\Centers\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Centers\Http\Requests\Client\ListCentersRequest;
use Modules\Centers\Http\Requests\Client\ShowCenterRequest;
use Modules\Centers\Http\Resources\Client\CenterDetailsResource;
use Modules\Centers\Http\Resources\Client\CenterResource;
use Modules\Centers\Services\CenterDetailsService;
use Modules\Centers\Services\CenterSearchService;

#[Group(name: 'Client / Centers', description: 'Browse and search centers. Public, no authentication required.')]
class CenterController extends Controller
{
    public function __construct(
        private readonly CenterSearchService $service,
        private readonly CenterDetailsService $detailsService,
    ) {}

    /**
     * List/search centers.
     *
     * Paginated (15 per page, `?page=`). Only `visible` centers are
     * returned.
     *
     * **Filters (all optional):**
     * - `city_id`         — integer, must exist.
     * - `lat` / `lng`     — required together, and required when `order` is `nearest`.
     * - `search`          — matches the center name.
     * - `services_types`  — array of service types; matches centers with any of them.
     * - `tags`            — array of tag ids; matches centers with any of them.
     * - `bounds`          — `{north, south, east, west}`, all four required together.
     *
     * **Order:** `order` is `most_popular` (default, by `points` descending)
     * or `nearest` (by distance to `lat`/`lng`, ascending).
     */
    #[Response(status: 200, description: 'Paginated `CenterResource` collection.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function index(ListCentersRequest $request): AnonymousResourceCollection
    {
        return CenterResource::collection($this->service->search($request->validated()));
    }

    /**
     * Center details.
     *
     * Looked up by `slug`; only visible, active centers are returned. Includes
     * images, cover, logo, tags and an overview of the center's active services
     * (`name`, `cover`, `start_price` in halalas). Send `lat` and `lng` together
     * to get `distance_km`; otherwise it is `null`.
     */
    #[Response(status: 200, description: '`CenterDetailsResource`.')]
    #[Response(status: 404, description: 'Center not found or not visible.')]
    #[Response(status: 422, description: 'Validation failed.')]
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

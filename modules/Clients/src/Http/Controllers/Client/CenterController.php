<?php

namespace Modules\Clients\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Clients\Http\Requests\Client\ListCentersRequest;
use Modules\Clients\Http\Resources\Client\CenterResource;
use Modules\Clients\Services\CenterSearchService;

#[Group(name: 'Client / Centers', description: 'Browse and search centers. Public, no authentication required.')]
class CenterController extends Controller
{
    public function __construct(private readonly CenterSearchService $service) {}

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
}

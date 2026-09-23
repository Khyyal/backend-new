<?php

namespace Modules\Support\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Support\Http\Resources\CityResource;
use Modules\Support\Services\CityService;

#[Group(name: 'Client / Cities', description: 'Client-facing city endpoints.')]
class CityController extends Controller
{
    /**
     * List cities with visible centers.
     *
     * Returns all cities that have at least one center with VISIBLE status,
     * ordered alphabetically by name. Only cities that contain at least one
     * published (`VISIBLE`) center are returned, so the client-side location
     * picker never shows empty cities.
     *
     * The response data is an array of `CityResource` items wrapped in a
     * standard `data` envelope.
     *
     * @return AnonymousResourceCollection<int, CityResource>
     */
    #[Response(
        status: 200,
        description: 'List of cities that have at least one visible center, wrapped in the `data` envelope as `CityResource[]`.'
    )]
    public function index(CityService $cityService): AnonymousResourceCollection
    {
        return CityResource::collection($cityService->getWithCenters());
    }
}

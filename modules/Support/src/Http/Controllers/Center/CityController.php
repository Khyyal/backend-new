<?php

namespace Modules\Support\Http\Controllers\Center;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Support\Http\Resources\CityResource;
use Modules\Support\Services\CityService;

#[Group(name: 'Center / Cities', description: 'Center protal city endpoints.')]
class CityController extends Controller
{
    /**
     * List all cities.
     *
     * Returns every city in the system ordered alphabetically by name,
     * regardless of whether the city has any centers. Used by the center
     * portal for full-system city listings and location pickers.
     *
     * The response data is an array of `CityResource` items wrapped in a
     * standard `data` envelope.
     *
     * @return AnonymousResourceCollection<int, CityResource>
     */
    #[Response(
        status: 200,
        description: 'Complete list of all cities, wrapped in the `data` envelope as `CityResource[]`.'
    )]
    public function index(CityService $cityService): AnonymousResourceCollection
    {
        return CityResource::collection($cityService->getAll());
    }
}

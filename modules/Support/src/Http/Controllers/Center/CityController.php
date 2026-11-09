<?php

namespace Modules\Support\Http\Controllers\Center;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Support\Http\Resources\CityResource;
use Modules\Support\Models\City;
use Modules\Support\Services\CityService;

#[Group('Center / Cities', 'City lookup for the center portal (registration and location pickers).')]
class CityController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, CityResource>
     */
    #[Unauthenticated]
    #[Endpoint('List all cities', 'Every city ordered by name, whether or not it has centers. Not paginated. Names follow `Accept-Language`.')]
    #[ResponseFromApiResource(CityResource::class, City::class, status: 200, description: 'All cities.', collection: true)]
    public function index(CityService $cityService): AnonymousResourceCollection
    {
        return CityResource::collection($cityService->getAll());
    }
}

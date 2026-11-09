<?php

namespace Modules\Support\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Support\Http\Resources\CityResource;
use Modules\Support\Models\City;
use Modules\Support\Services\CityService;

#[Group('Client / Cities', 'City lookup for the client app.')]
class CityController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, CityResource>
     */
    #[Unauthenticated]
    #[Endpoint('List cities with visible centers', 'Cities having at least one published (visible) center, ordered by name, so the location picker never shows empty cities. Not paginated.')]
    #[ResponseFromApiResource(CityResource::class, City::class, status: 200, description: 'Cities with visible centers.', collection: true)]
    public function index(CityService $cityService): AnonymousResourceCollection
    {
        return CityResource::collection($cityService->getWithCenters());
    }
}

<?php

namespace Modules\Support\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Collection;
use Modules\Support\Services\CityService;

#[Group(name: 'Client / Cities', description: 'Client-facing city endpoints.')]
class CityController extends Controller
{
    /**
     * List cities with visible centers.
     *
     * Returns all cities that have at least one center with VISIBLE status,
     * ordered alphabetically by name.
     *
     * @return Collection<int, \Modules\Support\Models\City>
     */
    #[Response(status: 200, description: 'List of cities that have at least one visible center.')]
    public function index(CityService $cityService): Collection
    {
        return $cityService->getWithCenters();
    }
}

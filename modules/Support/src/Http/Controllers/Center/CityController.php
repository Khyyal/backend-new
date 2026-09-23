<?php

namespace Modules\Support\Http\Controllers\Center;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Collection;
use Modules\Support\Services\CityService;

#[Group(name: 'Center / Cities', description: 'Center dashboard city endpoints.')]
class CityController extends Controller
{
    /**
     * List all cities.
     *
     * Returns every city in the system ordered alphabetically by name,
     * regardless of whether the city has any centers.
     *
     * @return Collection<int, \Modules\Support\Models\City>
     */
    #[Response(status: 200, description: 'Complete list of all cities.')]
    public function index(CityService $cityService): Collection
    {
        return $cityService->getAll();
    }
}

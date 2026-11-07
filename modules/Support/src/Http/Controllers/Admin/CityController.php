<?php

namespace Modules\Support\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Support\Http\Requests\Admin\StoreCityRequest;
use Modules\Support\Http\Requests\Admin\UpdateCityRequest;
use Modules\Support\Http\Resources\Admin\CityResource;
use Modules\Support\Models\City;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Cities', description: 'System user management of cities. Requires a system user Bearer token with the matching `cities.*` permission.')]
class CityController extends Controller
{
    /**
     * List cities.
     *
     * Paginated (15 per page, `per_page` up to 100), ordered by id, each with its `centers_count`.
     */
    #[QueryParameter('search', description: 'Matches the English or Arabic name.', type: 'string')]
    #[Response(status: 200, description: 'Paginated `CityResource` collection.')]
    #[Response(status: 403, description: 'Missing the `cities.view_any` permission.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', City::class);

        $cities = City::query()
            ->withCount('centers')
            ->when($request->query('search'), fn ($query, string $term) => $query->where(
                fn ($query) => $query->where('name->en', 'like', "%{$term}%")->orWhere('name->ar', 'like', "%{$term}%")
            ))
            ->orderBy('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return CityResource::collection($cities);
    }

    /**
     * Create a city.
     *
     * **Request body:** `name` (`{en, ar}`), `lat`, `lng`, `radius` (km, 1-1000), all required.
     */
    #[Response(status: 201, description: 'The created `CityResource`.')]
    #[Response(status: 403, description: 'Missing the `cities.create` permission.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = City::query()->create($request->validated());

        return CityResource::make($city->loadCount('centers'))->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a city.
     */
    #[Response(status: 200, description: 'The `CityResource`.')]
    #[Response(status: 403, description: 'Missing the `cities.view` permission.')]
    #[Response(status: 404, description: 'No city with that id.')]
    public function show(Request $request, City $city): CityResource
    {
        Gate::forUser($request->user())->authorize('view', $city);

        return CityResource::make($city->loadCount('centers'));
    }

    /**
     * Update a city.
     *
     * Partial update; send `name` with both `en` and `ar`.
     */
    #[Response(status: 200, description: 'The updated `CityResource`.')]
    #[Response(status: 403, description: 'Missing the `cities.update` permission.')]
    #[Response(status: 404, description: 'No city with that id.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $city->update($request->validated());

        return CityResource::make($city->loadCount('centers'));
    }

    /**
     * Delete a city.
     *
     * Refused (422) while centers still belong to the city.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'Missing the `cities.delete` permission.')]
    #[Response(status: 404, description: 'No city with that id.')]
    #[Response(status: 422, description: 'The city still has centers.')]
    public function destroy(Request $request, City $city): \Illuminate\Http\Response
    {
        Gate::forUser($request->user())->authorize('delete', $city);

        if ($city->centers()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['city' => ['The city still has centers.']]);
        }

        try {
            $city->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages(['city' => ['The city is still in use.']]);
        }

        return response()->noContent();
    }
}

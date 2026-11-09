<?php

namespace Modules\Support\Http\Controllers\Admin;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Requests\Admin\StoreCityRequest;
use Modules\Support\Http\Requests\Admin\UpdateCityRequest;
use Modules\Support\Http\Resources\Admin\CityResource;
use Modules\Support\Models\City;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Cities', 'Cities that centers belong to. Requires the matching `cities.*` permission.')]
class CityController extends Controller
{
    #[Endpoint('List cities', 'Ordered by ID, paginated, with `centers_count`.')]
    #[QueryParam('search', 'string', 'Matches the English or Arabic name (contains).', required: false, example: 'Riy')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(CityResource::class, City::class, status: 200, description: 'Paginated cities.', paginate: 15, withCount: ['centers'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `cities.view_any`.')]
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

    #[Endpoint('Create a city')]
    #[ResponseFromApiResource(CityResource::class, City::class, status: 201, description: 'The created city.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `cities.create`.')]
    #[ApiResponse(['message' => 'The name.ar field is required.', 'errors' => ['name.ar' => ['The name.ar field is required.']]], 422, 'Validation failed.')]
    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = City::query()->create($request->validated());

        return CityResource::make($city->loadCount('centers'))->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a city')]
    #[UrlParam('city', 'integer', 'City ID.', example: 1)]
    #[ResponseFromApiResource(CityResource::class, City::class, status: 200, description: 'The city.', withCount: ['centers'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `cities.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No city with that ID.')]
    public function show(Request $request, City $city): CityResource
    {
        Gate::forUser($request->user())->authorize('view', $city);

        return CityResource::make($city->loadCount('centers'));
    }

    #[Endpoint('Update a city', 'Partial update; when `name` is sent it needs both `en` and `ar`.')]
    #[UrlParam('city', 'integer', 'City ID.', example: 1)]
    #[ResponseFromApiResource(CityResource::class, City::class, status: 200, description: 'The updated city.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `cities.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No city with that ID.')]
    #[ApiResponse(['message' => 'The name.ar field is required.', 'errors' => ['name.ar' => ['The name.ar field is required.']]], 422, 'Validation failed.')]
    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $city->update($request->validated());

        return CityResource::make($city->loadCount('centers'));
    }

    #[Endpoint('Delete a city', 'Refused while any center (including deleted ones) belongs to it.')]
    #[UrlParam('city', 'integer', 'City ID.', example: 1)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `cities.delete`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No city with that ID.')]
    #[ApiResponse(['message' => 'The city still has centers.', 'errors' => ['city' => ['The city still has centers.']]], 422, 'The city still has centers or is still referenced.')]
    public function destroy(Request $request, City $city): Response
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

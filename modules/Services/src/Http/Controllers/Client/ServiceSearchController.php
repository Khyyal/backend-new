<?php

namespace Modules\Services\Http\Controllers\Client;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Services\Http\Requests\Client\SearchServicesRequest;
use Modules\Services\Http\Resources\Client\ServiceSearchResource;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;

#[Group('Client / Services', 'Browse services. Public: no token needed. Names follow `Accept-Language`.')]
class ServiceSearchController extends Controller
{
    #[Unauthenticated]
    #[Endpoint('Search services', 'Active services of visible, active centers, newest first, paginated. Filters combine.')]
    #[ResponseFromApiResource(ServiceSearchResource::class, Service::class, status: 200, description: 'Paginated services.', paginate: 15)]
    #[ApiResponse(['message' => 'The selected types.0 is invalid.', 'errors' => ['types.0' => ['The selected types.0 is invalid.']]], 422, 'Invalid filter.')]
    public function __invoke(SearchServicesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $services = Service::query()
            ->where('status', ActivationStatus::ACTIVE->value)
            ->whereHas('center', fn ($center) => $center->visible()
                ->when($filters['city_id'] ?? null, fn ($query, int $cityId) => $query->where('city_id', $cityId)))
            ->when($filters['center_id'] ?? null, fn ($query, int $centerId) => $query->where('center_id', $centerId))
            ->when($filters['types'] ?? null, fn ($query, array $types) => $query->whereIn('type', $types))
            ->when($filters['search'] ?? null, function ($query, string $term) {
                $term = addcslashes($term, '%_\\');

                $query->where(fn ($query) => $query
                    ->where('name->en', 'like', "%{$term}%")
                    ->orWhere('name->ar', 'like', "%{$term}%")
                    ->orWhere('description->en', 'like', "%{$term}%")
                    ->orWhere('description->ar', 'like', "%{$term}%"));
            })
            ->with('center')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ServiceSearchResource::collection($services);
    }
}

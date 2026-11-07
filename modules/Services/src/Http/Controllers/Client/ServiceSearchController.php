<?php

namespace Modules\Services\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Services\Http\Requests\Client\SearchServicesRequest;
use Modules\Services\Http\Resources\Client\ServiceSearchResource;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;

#[Group(name: 'Client / Services', description: 'Browse the services of a center. Public, no authentication required.')]
class ServiceSearchController extends Controller
{
    /**
     * Search services across all service types.
     *
     * Only `active` services of `visible`, active centers. Every filter is optional and they combine: `search`
     * (name or description, English or Arabic), `types[]` (service types), `city_id` (the center's city) and
     * `center_id`. Paginated (15 per page, `per_page` up to 50), newest first.
     * Open an item with `GET /clients/centers/{center}/services/{slug}`.
     */
    #[Response(status: 200, description: 'Paginated `{id, slug, name, description, type, center: {id, name, slug}}`.')]
    #[Response(status: 422, description: 'Invalid filter.')]
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

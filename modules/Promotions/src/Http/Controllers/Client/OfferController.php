<?php

namespace Modules\Promotions\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Promotions\Contracts\ServicePriceDisplay;
use Modules\Promotions\Http\Resources\OfferResource;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\OfferCatalog;
use Modules\Promotions\Services\PromotionServiceFinder;
use Modules\Services\Models\Service;

#[Group(name: 'Client / Offers', description: 'Running promotions. Amounts are minor units (halalas); a percentage `discount_value` is in basis points.')]
class OfferController extends Controller
{
    public function __construct(
        private readonly OfferCatalog $catalog,
        private readonly PromotionServiceFinder $serviceFinder,
        private readonly ServicePriceDisplay $priceDisplay,
    ) {}

    /**
     * List running offers.
     *
     * Offers the client has used up, or is not eligible for, are left out.
     */
    #[QueryParameter('center_id', description: "Only this center's offers and platform-wide ones.", type: 'int')]
    #[ApiResponse(status: 200, description: '`OfferResource` collection.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $offers = $this->catalog->running(
            $request->user()->getKey(),
            $request->filled('center_id') ? $request->integer('center_id') : null,
        );

        return OfferResource::collection($offers);
    }

    /**
     * Show an offer with the services it covers.
     *
     * Services are paginated; `display_price` is set when an automatic offer
     * lowers the service's start price right now. Returns 404 unless the
     * promotion is active and inside its date window.
     */
    #[ApiResponse(status: 200, description: '`{data: OfferResource & {services: [{id, center_id, name, type, start_price, display_price}]}, meta}`.')]
    #[ApiResponse(status: 404, description: 'Not a running offer.')]
    public function show(Request $request, Promotion $promotion): JsonResponse
    {
        abort_unless($promotion->isActiveAt(now()), 404);

        $promotion->load(['center', 'schedules', 'media']);

        $services = $this->serviceFinder->query($promotion)
            ->withMin('priceOptions as start_price', 'price')
            ->orderBy('services.id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        $prices = $this->priceDisplay->forServices($services->getCollection());

        return response()->json([
            'data' => array_merge(OfferResource::make($promotion)->resolve($request), [
                'services' => $services->getCollection()->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'center_id' => $service->center_id,
                    'name' => $service->name,
                    'type' => $service->type->value,
                    'start_price' => $service->start_price !== null ? (int) $service->start_price : null,
                    'display_price' => $prices[$service->id] ?? null,
                ])->values(),
            ]),
            'meta' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
            ],
        ]);
    }
}

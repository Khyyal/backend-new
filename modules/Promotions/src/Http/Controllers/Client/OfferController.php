<?php

namespace Modules\Promotions\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Promotions\Contracts\ServicePriceDisplay;
use Modules\Promotions\Http\Resources\OfferResource;
use Modules\Promotions\Models\Promotion;
use Modules\Promotions\Services\OfferCatalog;
use Modules\Promotions\Services\PromotionServiceFinder;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Offers', 'Running promotions the client can use. Amounts are halalas; a percentage `discount_value` is in basis points. Localized by `Accept-Language` (`en`/`ar`).')]
class OfferController extends Controller
{
    public function __construct(
        private readonly OfferCatalog $catalog,
        private readonly PromotionServiceFinder $serviceFinder,
        private readonly ServicePriceDisplay $priceDisplay,
    ) {}

    #[Endpoint('List running offers', 'Active offers inside their date range. Offers the client has used up, or is not eligible for (e.g. first-booking-only after a booking), are left out. Not paginated.')]
    #[QueryParam('center_id', 'integer', 'Only this center\'s offers plus platform-wide ones.', required: false, example: 8)]
    #[ResponseFromApiResource(OfferResource::class, Promotion::class, status: 200, description: 'Running offers.', collection: true, with: ['center', 'schedules'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $offers = $this->catalog->running(
            $request->user()->getKey(),
            $request->filled('center_id') ? $request->integer('center_id') : null,
        );

        return OfferResource::collection($offers);
    }

    #[Endpoint('Show an offer', 'The offer with the services it covers, paginated. `display_price` is set when an automatic offer lowers the service start price right now. 404 unless the promotion is active and inside its date window.')]
    #[UrlParam('promotion', 'integer', 'Promotion ID.', example: 12)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ApiResponse(['data' => ['id' => 12, 'name' => '15% off rides', 'description' => 'Weekend riding discount.', 'badge_label' => '-15%', 'scope' => 'item', 'discount_type' => 'percentage', 'discount_value' => 1500, 'max_discount_amount' => 5000, 'min_subtotal_amount' => null, 'requires_coupon' => false, 'first_booking_only' => false, 'starts_at' => '2026-06-01T00:00:00+00:00', 'ends_at' => '2026-08-31T23:59:59+00:00', 'cover' => null, 'center' => ['id' => 8, 'name' => 'Al Fursan Stables', 'slug' => 'al-fursan'], 'schedules' => [['day_of_week' => 5, 'start_time' => '16:00', 'end_time' => '23:00']], 'services' => [['id' => 31, 'center_id' => 8, 'name' => 'Beginner ride', 'type' => 'recreation_riding', 'start_price' => 15000, 'display_price' => 12750]]], 'meta' => ['current_page' => 1, 'per_page' => 15, 'total' => 1, 'last_page' => 1]], 200, 'The offer with paginated services.')]
    #[ResponseField('data', 'object', 'The offer: every `OfferResource` field (id, name, description, badge_label, scope, discount_type, discount_value, max_discount_amount, min_subtotal_amount, requires_coupon, first_booking_only, starts_at, ends_at, cover, center, schedules) plus `services`.')]
    #[ResponseField('data.services', 'object[]', 'Services the offer covers (current page).')]
    #[ResponseField('data.services[].id', 'integer', 'Service ID.', example: 31)]
    #[ResponseField('data.services[].center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('data.services[].name', 'string', 'Service name in the request locale.', example: 'Beginner ride')]
    #[ResponseField('data.services[].type', 'string', 'Service type.', enum: ServiceType::class, example: 'recreation_riding')]
    #[ResponseField('data.services[].start_price', 'integer', 'Lowest price option in halalas; null when the service has none.', nullable: true, example: 15000)]
    #[ResponseField('data.services[].display_price', 'integer', 'Discounted start price in halalas when an automatic offer lowers it right now; otherwise null.', nullable: true, example: 12750)]
    #[ResponseField('meta.current_page', 'integer', 'Current services page.', example: 1)]
    #[ResponseField('meta.per_page', 'integer', 'Services per page.', example: 15)]
    #[ResponseField('meta.total', 'integer', 'Total covered services.', example: 1)]
    #[ResponseField('meta.last_page', 'integer', 'Last services page.', example: 1)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Not a running offer.')]
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

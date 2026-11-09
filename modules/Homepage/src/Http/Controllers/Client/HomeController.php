<?php

namespace Modules\Homepage\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Http\Requests\Client\HomeEventsRequest;
use Modules\Homepage\Http\Requests\Client\HomeRequest;
use Modules\Homepage\Services\ContentEventRecorder;
use Modules\Homepage\Services\HomeContentService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Client / Home', 'The curated client home page. Public: no token needed. Texts and banner images follow `Accept-Language`.')]
class HomeController extends Controller
{
    public function __construct(
        private readonly HomeContentService $content,
        private readonly ContentEventRecorder $recorder,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Get the home page', 'Published sections within their schedule, in order, each with its first items. Sections with nothing to show are left out.')]
    #[ApiResponse(['data' => [['id' => 2, 'key' => 'summer-deals', 'type' => 'curated_promotions', 'title' => 'Summer deals', 'subtitle' => 'Limited time', 'items' => [['id' => 12, 'name' => '15% off rides', 'scope' => 'item', 'discount_type' => 'percentage', 'discount_value' => 1500, 'requires_coupon' => false]], 'has_more' => false]]], 200, 'Sections.')]
    #[ResponseField('data[].id', 'integer', 'Section ID.', example: 2)]
    #[ResponseField('data[].key', 'string', 'Section key.', example: 'summer-deals')]
    #[ResponseField('data[].type', 'string', 'Section type; decides the item shape.', enum: HomeSectionType::class, example: 'curated_promotions')]
    #[ResponseField('data[].title', 'string', 'Title in the request locale.', nullable: true, example: 'Summer deals')]
    #[ResponseField('data[].subtitle', 'string', 'Subtitle in the request locale.', nullable: true, example: 'Limited time')]
    #[ResponseField('data[].items', 'object[]', 'Items by type: `banner_carousel` → client `BannerResource`; `curated_centers`/`top_centers` → client `CenterResource`; `curated_services` → `HomeServiceResource`; `curated_promotions` → `OfferResource`; `curated_campaigns` → `HomeCampaignResource`. Money in halalas.')]
    #[ResponseField('data[].has_more', 'boolean', 'More items than shown; open the section endpoint.', example: false)]
    #[ApiResponse(['message' => 'The lng field is required when lat is present.', 'errors' => ['lng' => ['The lng field is required when lat is present.']]], 422, 'Validation failed.')]
    public function index(HomeRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->content->compose($request->latitude(), $request->longitude()),
        ]);
    }

    #[Unauthenticated]
    #[Endpoint('Get one home section', 'Paginated items of one visible section, same item shapes as the home page.')]
    #[UrlParam('key', 'string', 'Section key.', example: 'summer-deals')]
    #[ApiResponse(['data' => ['id' => 2, 'key' => 'summer-deals', 'type' => 'curated_promotions', 'title' => 'Summer deals', 'subtitle' => 'Limited time', 'items' => [['id' => 12, 'name' => '15% off rides', 'scope' => 'item', 'discount_type' => 'percentage', 'discount_value' => 1500, 'requires_coupon' => false]], 'has_more' => false], 'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1, 'last_page' => 1]], 200, 'The section and pagination.')]
    #[ResponseField('data.id', 'integer', 'Section ID.', example: 2)]
    #[ResponseField('data.key', 'string', 'Section key.', example: 'summer-deals')]
    #[ResponseField('data.type', 'string', 'Section type; decides the item shape.', enum: HomeSectionType::class, example: 'curated_promotions')]
    #[ResponseField('data.title', 'string', 'Title in the request locale.', nullable: true, example: 'Summer deals')]
    #[ResponseField('data.subtitle', 'string', 'Subtitle in the request locale.', nullable: true, example: 'Limited time')]
    #[ResponseField('data.items', 'object[]', 'Items by type: `banner_carousel` → client `BannerResource`; `curated_centers`/`top_centers` → client `CenterResource`; `curated_services` → `HomeServiceResource`; `curated_promotions` → `OfferResource`; `curated_campaigns` → `HomeCampaignResource`. Money in halalas.')]
    #[ResponseField('data.has_more', 'boolean', 'More items than shown; open the section endpoint.', example: false)]
    #[ResponseField('meta', 'object', 'Pagination: `current_page`, `per_page`, `total`, `last_page`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No visible section with that key.')]
    #[ApiResponse(['message' => 'The per page field must be between 1 and 100.', 'errors' => ['per_page' => ['The per page field must be between 1 and 100.']]], 422, 'Validation failed.')]
    public function section(HomeRequest $request, string $key): JsonResponse
    {
        $result = $this->content->section(
            $key,
            $request->latitude(),
            $request->longitude(),
            (int) $request->validated('page', 1),
            $request->validated('per_page') !== null ? (int) $request->validated('per_page') : null,
        );

        abort_if($result === null, 404);

        return response()->json($result);
    }

    #[Unauthenticated]
    #[Endpoint('Report home content events', 'Batched impressions and clicks of sections, banners and their items. Aggregated per day with nothing identifying stored; events for unknown entities are ignored. Limited to 60 requests per minute per client or IP. A client token is optional.')]
    #[ApiResponse(['data' => ['accepted' => 12]], 202, 'Events counted.')]
    #[ResponseField('data.accepted', 'integer', 'Events counted.', example: 12)]
    #[ApiResponse(['message' => 'The selected events.0.type is invalid.', 'errors' => ['events.0.type' => ['The selected events.0.type is invalid.']]], 422, 'Validation failed.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function events(HomeEventsRequest $request): JsonResponse
    {
        $accepted = $this->recorder->record($request->validated('events'));

        return response()->json(['data' => ['accepted' => $accepted]], ResponseAlias::HTTP_ACCEPTED);
    }
}

<?php

namespace Modules\Homepage\Http\Controllers\Admin;

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
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Http\Requests\Admin\ItemCandidatesRequest;
use Modules\Homepage\Http\Requests\Admin\ReorderHomeSectionsRequest;
use Modules\Homepage\Http\Requests\Admin\StoreHomeSectionRequest;
use Modules\Homepage\Http\Requests\Admin\SyncHomeSectionItemsRequest;
use Modules\Homepage\Http\Requests\Admin\UpdateHomeSectionRequest;
use Modules\Homepage\Http\Resources\Admin\HomeSectionResource;
use Modules\Homepage\Models\HomeSection;
use Modules\Homepage\Services\HomeSectionService;
use Modules\Homepage\Services\ItemCatalog;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Home page', 'Sections, curated items and banners of the client home page. Requires `homepage.manage`.')]
class HomeSectionController extends Controller
{
    public function __construct(
        private readonly HomeSectionService $sections,
        private readonly ItemCatalog $catalog,
    ) {}

    #[Endpoint('List sections', 'In display order, paginated, without items.')]
    #[QueryParam('status', 'string', 'Only this status.', required: false, enum: PublishStatus::class, example: 'published')]
    #[QueryParam('type', 'string', 'Only this type.', required: false, enum: HomeSectionType::class, example: 'banner_carousel')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(HomeSectionResource::class, HomeSection::class, status: 200, description: 'Paginated sections.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', HomeSection::class);

        $sections = HomeSection::query()
            ->withCount(['items', 'banners'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return HomeSectionResource::collection($sections);
    }

    #[Endpoint('Create a section', 'Draft unless `status` is sent.')]
    #[ResponseFromApiResource(HomeSectionResource::class, HomeSection::class, status: 201, description: 'The created section.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(['message' => 'The key has already been taken.', 'errors' => ['key' => ['The key has already been taken.']]], 422, 'Validation failed.')]
    public function store(StoreHomeSectionRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', HomeSection::class);

        $section = $this->sections->create($request->validated(), $request->user());

        return $this->resource($section->load('items'))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a section', 'With its items and their summaries.')]
    #[UrlParam('section', 'integer', 'Section ID.', example: 2)]
    #[ResponseFromApiResource(HomeSectionResource::class, HomeSection::class, status: 200, description: 'The section.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such section.')]
    public function show(Request $request, HomeSection $section): HomeSectionResource
    {
        Gate::forUser($request->user())->authorize('view', $section);

        return $this->resource($section->load('items')->loadCount('banners'));
    }

    #[Endpoint('Update a section', 'Partial update; `config` keys are merged.')]
    #[UrlParam('section', 'integer', 'Section ID.', example: 2)]
    #[ResponseFromApiResource(HomeSectionResource::class, HomeSection::class, status: 200, description: 'The updated section.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such section.')]
    #[ApiResponse(['message' => 'The type field is prohibited.', 'errors' => ['type' => ['The type field is prohibited.']]], 422, 'Validation failed.')]
    public function update(UpdateHomeSectionRequest $request, HomeSection $section): HomeSectionResource
    {
        Gate::forUser($request->user())->authorize('update', $section);

        return $this->resource($this->sections->update($section, $request->validated())->load('items'));
    }

    #[Endpoint('Delete a section', 'Its banners are kept, detached.')]
    #[UrlParam('section', 'integer', 'Section ID.', example: 2)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such section.')]
    public function destroy(Request $request, HomeSection $section): Response
    {
        Gate::forUser($request->user())->authorize('delete', $section);

        $section->delete();

        return response()->noContent();
    }

    #[Endpoint('Reorder sections')]
    #[ApiResponse(['data' => ['order' => [3, 1, 2]]], 200, 'Every section ID in the new display order.')]
    #[ResponseField('data.order', 'integer[]', 'Section IDs in display order.', example: [3, 1, 2])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(['message' => 'The selected order.0 is invalid.', 'errors' => ['order.0' => ['The selected order.0 is invalid.']]], 422, 'Validation failed.')]
    public function reorder(ReorderHomeSectionsRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', HomeSection::class);

        $this->sections->reorder($request->validated('order'));

        return response()->json(['data' => ['order' => HomeSection::query()->orderBy('sort_order')->orderBy('id')->pluck('id')]]);
    }

    #[Endpoint('Replace the items of a curated section', 'Every item must be of the section `item_type`. Items clients cannot see now (hidden center, inactive service, ended promotion) are kept but skipped on the home page.')]
    #[UrlParam('section', 'integer', 'Section ID.', example: 2)]
    #[ResponseFromApiResource(HomeSectionResource::class, HomeSection::class, status: 200, description: 'The section with its new items.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such section.')]
    #[ApiResponse(['message' => 'This section can only hold items of type "promotion".', 'errors' => ['items.0.type' => ['This section can only hold items of type "promotion".']]], 422, 'Validation failed, wrong item type, or the section holds no items.')]
    public function syncItems(SyncHomeSectionItemsRequest $request, HomeSection $section): HomeSectionResource
    {
        Gate::forUser($request->user())->authorize('update', $section);

        return $this->resource($this->sections->syncItems($section, $request->validated('items')));
    }

    #[Endpoint('Search records to pick into a section', 'Records of the section `item_type`, newest first, matched by name or exact ID.')]
    #[UrlParam('section', 'integer', 'Section ID.', example: 2)]
    #[ApiResponse(['data' => [['type' => 'promotion', 'id' => 12, 'label' => '15% off rides', 'subtitle' => 'Al Fursan Stables', 'thumb' => null, 'is_available' => true]], 'meta' => ['current_page' => 1, 'per_page' => 15, 'total' => 1, 'last_page' => 1]], 200, 'Paginated candidates.')]
    #[ResponseField('data[].type', 'string', 'Item kind.', example: 'promotion')]
    #[ResponseField('data[].id', 'integer', 'Record ID.', example: 12)]
    #[ResponseField('data[].label', 'string', 'Name.', example: '15% off rides')]
    #[ResponseField('data[].subtitle', 'string', 'Secondary text.', nullable: true, example: 'Al Fursan Stables')]
    #[ResponseField('data[].thumb', 'string', 'Thumbnail URL.', nullable: true, example: 'No-example')]
    #[ResponseField('data[].is_available', 'boolean', 'Visible to clients right now.', example: true)]
    #[ResponseField('meta.current_page', 'integer', 'Current page.', example: 1)]
    #[ResponseField('meta.per_page', 'integer', 'Per page.', example: 15)]
    #[ResponseField('meta.total', 'integer', 'Total matches.', example: 1)]
    #[ResponseField('meta.last_page', 'integer', 'Last page.', example: 1)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such section.')]
    #[ApiResponse(['message' => 'This section type does not hold hand-picked items.', 'errors' => ['section' => ['This section type does not hold hand-picked items.']]], 422, 'The section type holds no items.')]
    public function itemCandidates(ItemCandidatesRequest $request, HomeSection $section): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $section);

        $itemType = $section->type->itemType();

        if ($itemType === null) {
            throw ValidationException::withMessages(['section' => __('homepage::messages.items_not_supported')]);
        }

        $candidates = $this->catalog->candidates(
            $itemType,
            $request->validated('search'),
            (int) $request->validated('per_page', 15),
            (int) $request->validated('page', 1),
        );

        return response()->json([
            'data' => $candidates->items(),
            'meta' => [
                'current_page' => $candidates->currentPage(),
                'per_page' => $candidates->perPage(),
                'total' => $candidates->total(),
                'last_page' => $candidates->lastPage(),
            ],
        ]);
    }

    private function resource(HomeSection $section): HomeSectionResource
    {
        $summaries = $section->relationLoaded('items') ? $this->catalog->summaries($section->items) : [];

        return HomeSectionResource::make($section)->withItemSummaries($summaries);
    }
}

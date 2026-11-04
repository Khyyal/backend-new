<?php

namespace Modules\Homepage\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Homepage\Http\Requests\Admin\ItemCandidatesRequest;
use Modules\Homepage\Http\Requests\Admin\ReorderHomeSectionsRequest;
use Modules\Homepage\Http\Requests\Admin\StoreHomeSectionRequest;
use Modules\Homepage\Http\Requests\Admin\SyncHomeSectionItemsRequest;
use Modules\Homepage\Http\Requests\Admin\UpdateHomeSectionRequest;
use Modules\Homepage\Http\Resources\Admin\HomeSectionResource;
use Modules\Homepage\Models\HomeSection;
use Modules\Homepage\Services\HomeSectionService;
use Modules\Homepage\Services\ItemCatalog;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Home page', description: 'Sections, curated items and banners of the client home page. Requires `homepage.manage`.')]
class HomeSectionController extends Controller
{
    public function __construct(
        private readonly HomeSectionService $sections,
        private readonly ItemCatalog $catalog,
    ) {}

    /**
     * List sections, in display order.
     */
    #[QueryParameter('status', description: '`draft`, `published` or `archived`.', type: 'string')]
    #[QueryParameter('type', description: 'A section type.', type: 'string')]
    #[ApiResponse(status: 200, description: 'Paginated `HomeSectionResource` collection (without items).')]
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

    /**
     * Create a section.
     *
     * Created as a draft unless `status` is sent. `type` is fixed at creation:
     * `banner_carousel` shows the banners attached to it; `curated_centers`,
     * `curated_services`, `curated_promotions` and `curated_campaigns` show the
     * items picked with the items endpoint; `top_centers` fills itself (nearest
     * first when the client sends its position, else most popular).
     * `config.limit` (1-50, default 10) is how many items the home page shows;
     * `config.radius_km` (centers, services, banners, top centers) hides
     * content farther than that from the client.
     */
    #[ApiResponse(status: 201, description: 'The created `HomeSectionResource`.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function store(StoreHomeSectionRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', HomeSection::class);

        $section = $this->sections->create($request->validated(), $request->user());

        return $this->resource($section->load('items'))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a section with its items.
     */
    #[ApiResponse(status: 200, description: 'The `HomeSectionResource`, items included with their summaries.')]
    public function show(Request $request, HomeSection $section): HomeSectionResource
    {
        Gate::forUser($request->user())->authorize('view', $section);

        return $this->resource($section->load('items')->loadCount('banners'));
    }

    /**
     * Update a section.
     *
     * `config` keys are merged into the stored config; send a key as null to remove it.
     */
    #[ApiResponse(status: 200, description: 'The updated `HomeSectionResource`.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function update(UpdateHomeSectionRequest $request, HomeSection $section): HomeSectionResource
    {
        Gate::forUser($request->user())->authorize('update', $section);

        return $this->resource($this->sections->update($section, $request->validated())->load('items'));
    }

    /**
     * Delete a section. Its banners are kept, without a section.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
    public function destroy(Request $request, HomeSection $section): Response
    {
        Gate::forUser($request->user())->authorize('delete', $section);

        $section->delete();

        return response()->noContent();
    }

    /**
     * Reorder sections.
     *
     * Sets the display order to the given list of section ids. Sections not
     * listed keep their relative order after the listed ones.
     */
    #[ApiResponse(status: 200, description: '`{data: {order: int[]}}`, every section id in display order.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function reorder(ReorderHomeSectionsRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', HomeSection::class);

        $this->sections->reorder($request->validated('order'));

        return response()->json(['data' => ['order' => HomeSection::query()->orderBy('sort_order')->orderBy('id')->pluck('id')]]);
    }

    /**
     * Replace the picked items of a curated section.
     *
     * Every item must be of the section's `item_type` (`curated_centers` takes
     * centers, `curated_services` services, and so on). List order is display
     * order; each item may have its own `starts_at`/`ends_at` window. Items
     * clients cannot see right now (hidden center, inactive service, ended
     * promotion) are kept but skipped on the home page.
     */
    #[ApiResponse(status: 200, description: 'The `HomeSectionResource` with its new items.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or the section type holds no items.')]
    public function syncItems(SyncHomeSectionItemsRequest $request, HomeSection $section): HomeSectionResource
    {
        Gate::forUser($request->user())->authorize('update', $section);

        return $this->resource($this->sections->syncItems($section, $request->validated('items')));
    }

    /**
     * Search records to pick into a curated section.
     *
     * Returns records of the section's `item_type`, newest first, matched by
     * name (any locale) or exact id. `is_available` tells whether clients can
     * see the record right now.
     */
    #[QueryParameter('search', description: 'Name or id.', type: 'string')]
    #[ApiResponse(status: 200, description: 'Paginated `{type, id, label, subtitle, thumb, is_available}` list.')]
    #[ApiResponse(status: 422, description: 'The section type holds no items.')]
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

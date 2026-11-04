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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Homepage\Http\Requests\Admin\StoreBannerRequest;
use Modules\Homepage\Http\Requests\Admin\UpdateBannerRequest;
use Modules\Homepage\Http\Resources\Admin\BannerResource;
use Modules\Homepage\Models\Banner;
use Modules\Homepage\Services\BannerImages;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Home page', description: 'Sections, curated items and banners of the client home page. Requires `homepage.manage`.')]
class BannerController extends Controller
{
    public function __construct(
        private readonly BannerImages $images,
    ) {}

    /**
     * List banners.
     */
    #[QueryParameter('status', description: '`draft`, `published` or `archived`.', type: 'string')]
    #[QueryParameter('home_section_id', description: 'Banners of this section.', type: 'int')]
    #[QueryParameter('center_id', description: 'Banners tied to this center.', type: 'int')]
    #[ApiResponse(status: 200, description: 'Paginated `BannerResource` collection.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Banner::class);

        $banners = Banner::query()
            ->with('media')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('home_section_id'), fn ($query) => $query->where('home_section_id', $request->integer('home_section_id')))
            ->when($request->filled('center_id'), fn ($query) => $query->where('center_id', $request->integer('center_id')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return BannerResource::collection($banners);
    }

    /**
     * Create a banner.
     *
     * Created as a draft unless `status` is sent. `action_type` is `none`,
     * `url` (needs `action_url`) or `center`, `service`, `promotion`,
     * `campaign` (need `action_target_id`). `home_section_id` must be a
     * `banner_carousel` section. `center_id` ties the banner to a center: it is
     * hidden when the center is hidden or outside the section radius.
     * `images` holds one temporary media id per locale (`en`, `ar`); a locale
     * without an image falls back to the other one.
     */
    #[ApiResponse(status: 201, description: 'The created `BannerResource`.')]
    #[ApiResponse(status: 422, description: 'Validation failed, or an image id is not a usable temporary media.')]
    public function store(StoreBannerRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Banner::class);

        $banner = DB::transaction(function () use ($request): Banner {
            $banner = Banner::create(array_merge(Arr::except($request->validated(), 'images'), [
                'created_by' => $request->user()->getKey(),
            ]));
            $this->images->sync($banner, $request->user(), $request->validated());

            return $banner;
        });

        return BannerResource::make($banner->refresh()->load('media'))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a banner.
     */
    #[ApiResponse(status: 200, description: 'The `BannerResource`.')]
    public function show(Request $request, Banner $banner): BannerResource
    {
        Gate::forUser($request->user())->authorize('view', $banner);

        return BannerResource::make($banner->load('media'));
    }

    /**
     * Update a banner.
     *
     * `images` keys replace that locale's image; null removes it.
     */
    #[ApiResponse(status: 200, description: 'The updated `BannerResource`.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function update(UpdateBannerRequest $request, Banner $banner): BannerResource
    {
        Gate::forUser($request->user())->authorize('update', $banner);

        DB::transaction(function () use ($request, $banner): void {
            $banner->update(Arr::except($request->validated(), 'images'));
            $this->images->sync($banner, $request->user(), $request->validated());
        });

        return BannerResource::make($banner->refresh()->load('media'));
    }

    /**
     * Delete a banner and its images.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
    public function destroy(Request $request, Banner $banner): Response
    {
        Gate::forUser($request->user())->authorize('delete', $banner);

        $banner->delete();

        return response()->noContent();
    }
}

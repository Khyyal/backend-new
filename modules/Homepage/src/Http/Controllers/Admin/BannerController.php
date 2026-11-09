<?php

namespace Modules\Homepage\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Http\Requests\Admin\StoreBannerRequest;
use Modules\Homepage\Http\Requests\Admin\UpdateBannerRequest;
use Modules\Homepage\Http\Resources\Admin\BannerResource;
use Modules\Homepage\Models\Banner;
use Modules\Homepage\Services\BannerImages;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Home page', 'Sections, curated items and banners of the client home page. Requires `homepage.manage`.')]
class BannerController extends Controller
{
    public function __construct(
        private readonly BannerImages $images,
    ) {}

    #[Endpoint('List banners', 'Paginated.')]
    #[QueryParam('status', 'string', 'Only this status.', required: false, enum: PublishStatus::class, example: 'published')]
    #[QueryParam('home_section_id', 'integer', 'Banners of this section.', required: false, example: 2)]
    #[QueryParam('center_id', 'integer', 'Banners tied to this center.', required: false, example: 8)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(BannerResource::class, Banner::class, status: 200, description: 'Paginated banners.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
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

    #[Endpoint('Create a banner', 'Draft unless `status` is sent.')]
    #[ResponseFromApiResource(BannerResource::class, Banner::class, status: 201, description: 'The created banner.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(['message' => 'A banner can only belong to a banner carousel section.', 'errors' => ['home_section_id' => ['A banner can only belong to a banner carousel section.']]], 422, 'Validation failed, not a banner section, or an image UUID is not a usable temporary upload.')]
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

    #[Endpoint('Show a banner')]
    #[UrlParam('banner', 'integer', 'Banner ID.', example: 5)]
    #[ResponseFromApiResource(BannerResource::class, Banner::class, status: 200, description: 'The banner.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such banner.')]
    public function show(Request $request, Banner $banner): BannerResource
    {
        Gate::forUser($request->user())->authorize('view', $banner);

        return BannerResource::make($banner->load('media'));
    }

    #[Endpoint('Update a banner', 'Partial update; `images` keys replace that locale\'s image (null removes it).')]
    #[UrlParam('banner', 'integer', 'Banner ID.', example: 5)]
    #[ResponseFromApiResource(BannerResource::class, Banner::class, status: 200, description: 'The updated banner.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such banner.')]
    #[ApiResponse(['message' => 'The action url field must be a valid URL.', 'errors' => ['action_url' => ['The action url field must be a valid URL.']]], 422, 'Validation failed.')]
    public function update(UpdateBannerRequest $request, Banner $banner): BannerResource
    {
        Gate::forUser($request->user())->authorize('update', $banner);

        DB::transaction(function () use ($request, $banner): void {
            $banner->update(Arr::except($request->validated(), 'images'));
            $this->images->sync($banner, $request->user(), $request->validated());
        });

        return BannerResource::make($banner->refresh()->load('media'));
    }

    #[Endpoint('Delete a banner', 'Also deletes its images.')]
    #[UrlParam('banner', 'integer', 'Banner ID.', example: 5)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `homepage.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such banner.')]
    public function destroy(Request $request, Banner $banner): Response
    {
        Gate::forUser($request->user())->authorize('delete', $banner);

        $banner->delete();

        return response()->noContent();
    }
}

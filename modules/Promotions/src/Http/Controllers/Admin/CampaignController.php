<?php

namespace Modules\Promotions\Http\Controllers\Admin;

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
use Modules\Promotions\Enums\CampaignStatus;
use Modules\Promotions\Http\Requests\StoreCampaignRequest;
use Modules\Promotions\Http\Requests\UpdateCampaignRequest;
use Modules\Promotions\Http\Resources\CampaignResource;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Services\CoverMedia;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Campaigns', 'Marketing campaigns that group platform promotions. Requires `campaigns.manage`.')]
class CampaignController extends Controller
{
    public function __construct(
        private readonly CoverMedia $cover,
    ) {}

    #[Endpoint('List campaigns', 'Newest first, paginated, with promotion counts.')]
    #[QueryParam('status', 'string', 'Filter by status.', required: false, enum: CampaignStatus::class, example: 'active')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(CampaignResource::class, Campaign::class, status: 200, description: 'Paginated campaigns.', paginate: 15, withCount: ['promotions'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `campaigns.manage`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()
            ->with('media')
            ->withCount('promotions')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 15))));

        return CampaignResource::collection($campaigns);
    }

    #[Endpoint('Create a campaign', 'Status defaults to `draft`. `cover` takes a temporary upload UUID.')]
    #[ResponseFromApiResource(CampaignResource::class, Campaign::class, status: 201, description: 'The created campaign.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `campaigns.manage`.')]
    #[ApiResponse(['message' => 'The slug has already been taken.', 'errors' => ['slug' => ['The slug has already been taken.']]], 422, 'Validation failed (e.g. slug taken, end before start, invalid cover).')]
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Campaign::class);

        $campaign = DB::transaction(function () use ($request): Campaign {
            $campaign = Campaign::create(array_merge(Arr::except($request->validated(), 'cover'), [
                'created_by' => $request->user()->getKey(),
            ]));
            $this->cover->sync($campaign, $request->user(), $request->validated());

            return $campaign;
        });

        return CampaignResource::make($campaign->refresh()->load('media'))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a campaign')]
    #[UrlParam('campaign', 'integer', 'Campaign ID.', example: 3)]
    #[ResponseFromApiResource(CampaignResource::class, Campaign::class, status: 200, description: 'The campaign.', withCount: ['promotions'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Campaign not found.')]
    public function show(Request $request, Campaign $campaign): CampaignResource
    {
        Gate::forUser($request->user())->authorize('view', $campaign);

        return CampaignResource::make($campaign->load('media')->loadCount('promotions'));
    }

    #[Endpoint('Update a campaign', 'Partial update. Send `cover: null` to remove the cover.')]
    #[UrlParam('campaign', 'integer', 'Campaign ID.', example: 3)]
    #[ResponseFromApiResource(CampaignResource::class, Campaign::class, status: 200, description: 'The updated campaign.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Campaign not found.')]
    #[ApiResponse(['message' => 'The ends at field must be a date after starts at.', 'errors' => ['ends_at' => ['The ends at field must be a date after starts at.']]], 422, 'Validation failed.')]
    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        Gate::forUser($request->user())->authorize('update', $campaign);

        DB::transaction(function () use ($request, $campaign): void {
            $campaign->update(Arr::except($request->validated(), 'cover'));
            $this->cover->sync($campaign, $request->user(), $request->validated());
        });

        return CampaignResource::make($campaign->refresh()->load('media'));
    }

    #[Endpoint('Delete a campaign', 'Soft delete. Its promotions are kept, detached from the campaign.')]
    #[UrlParam('campaign', 'integer', 'Campaign ID.', example: 3)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Campaign not found.')]
    public function destroy(Request $request, Campaign $campaign): Response
    {
        Gate::forUser($request->user())->authorize('delete', $campaign);

        DB::transaction(function () use ($campaign): void {
            $campaign->promotions()->update(['campaign_id' => null]);
            $campaign->delete();
        });

        return response()->noContent();
    }
}

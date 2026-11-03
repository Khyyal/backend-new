<?php

namespace Modules\Promotions\Http\Controllers\Admin;

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
use Modules\Promotions\Http\Requests\StoreCampaignRequest;
use Modules\Promotions\Http\Requests\UpdateCampaignRequest;
use Modules\Promotions\Http\Resources\CampaignResource;
use Modules\Promotions\Models\Campaign;
use Modules\Promotions\Services\CoverMedia;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Promotions', description: 'Marketing campaigns that group platform promotions. Requires `campaigns.manage`.')]
class CampaignController extends Controller
{
    public function __construct(
        private readonly CoverMedia $cover,
    ) {}

    /**
     * List campaigns.
     */
    #[QueryParameter('status', description: '`draft`, `active` or `archived`.', type: 'string')]
    #[ApiResponse(status: 200, description: 'Paginated `CampaignResource` collection.')]
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

    /**
     * Create a campaign.
     */
    #[ApiResponse(status: 201, description: 'The created campaign.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
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

    /**
     * Show a campaign.
     */
    #[ApiResponse(status: 200, description: 'The `CampaignResource`.')]
    public function show(Request $request, Campaign $campaign): CampaignResource
    {
        Gate::forUser($request->user())->authorize('view', $campaign);

        return CampaignResource::make($campaign->load('media')->loadCount('promotions'));
    }

    /**
     * Update a campaign.
     */
    #[ApiResponse(status: 200, description: 'The updated campaign.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        Gate::forUser($request->user())->authorize('update', $campaign);

        DB::transaction(function () use ($request, $campaign): void {
            $campaign->update(Arr::except($request->validated(), 'cover'));
            $this->cover->sync($campaign, $request->user(), $request->validated());
        });

        return CampaignResource::make($campaign->refresh()->load('media'));
    }

    /**
     * Delete a campaign. Its promotions are kept, without a campaign.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
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

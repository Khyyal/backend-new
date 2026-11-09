<?php

namespace Modules\Notifications\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Enums\DeliveryStatus;
use Modules\Notifications\Http\Requests\Admin\NotificationCampaignRequest;
use Modules\Notifications\Http\Requests\Admin\ScheduleNotificationCampaignRequest;
use Modules\Notifications\Http\Resources\Admin\NotificationCampaignDeliveryResource;
use Modules\Notifications\Http\Resources\Admin\NotificationCampaignResource;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Models\NotificationCampaignDelivery;
use Modules\Notifications\Services\CampaignService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / Notification campaigns', 'Bulk push or WhatsApp campaigns to clients or center staff, sent now or scheduled. Requires `notification_campaigns.manage`.')]
class NotificationCampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
    ) {}

    #[Endpoint('List campaigns', 'Newest first, paginated.')]
    #[QueryParam('channel', 'string', 'Only this channel.', required: false, enum: CampaignChannel::class, example: 'push')]
    #[QueryParam('status', 'string', 'Only this status.', required: false, enum: CampaignStatus::class, example: 'draft')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 200, description: 'Paginated campaigns.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', NotificationCampaign::class);

        $campaigns = NotificationCampaign::query()
            ->with('template')
            ->when($request->query('channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return NotificationCampaignResource::collection($campaigns);
    }

    #[Endpoint('Create a campaign', 'Push: `title` and `body` in both locales plus an optional tap action. WhatsApp: `whatsapp_template_id` and one `template_parameters` binding per variable. Saved as a draft, as scheduled with `scheduled_at`, or sent at once with `send_now`.')]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 201, description: 'The created campaign.', with: ['targets', 'template'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(['message' => 'Select at least one target.', 'errors' => ['target_ids' => ['Select at least one target.']]], 422, 'Validation failed (missing content, wrong parameter count, field not for the audience, unknown targets).')]
    public function store(NotificationCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaigns->create($request->validated(), $request->user()?->getKey());

        if ($request->boolean('send_now')) {
            $this->campaigns->sendNow($campaign);
        }

        return NotificationCampaignResource::make($campaign->refresh()->load(['targets', 'template']))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a campaign', 'With targets and template.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 200, description: 'The campaign.', with: ['targets', 'template'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    public function show(Request $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        Gate::forUser($request->user())->authorize('view', $notificationCampaign);

        return NotificationCampaignResource::make($notificationCampaign->load(['targets', 'template']));
    }

    #[Endpoint('Update a campaign', 'Only draft or scheduled campaigns. Partial update; content fields follow the (new or current) channel.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 200, description: 'The updated campaign.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    #[ApiResponse(['message' => 'Only draft or scheduled campaigns can be changed.', 'errors' => ['status' => ['Only draft or scheduled campaigns can be changed.']]], 422, 'The campaign is no longer editable (only draft or scheduled).')]
    public function update(NotificationCampaignRequest $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        $campaign = $this->campaigns->update($notificationCampaign, $request->validated());

        return NotificationCampaignResource::make($campaign->refresh()->load(['targets', 'template']));
    }

    #[Endpoint('Delete a campaign', 'Draft, scheduled or finished campaigns; not while sending.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    #[ApiResponse(['message' => 'This campaign is already being sent or has finished.', 'errors' => ['status' => ['This campaign is already being sent or has finished.']]], 422, 'The campaign is being sent.')]
    public function destroy(Request $request, NotificationCampaign $notificationCampaign): Response
    {
        Gate::forUser($request->user())->authorize('delete', $notificationCampaign);

        if ($notificationCampaign->status === CampaignStatus::Sending) {
            throw ValidationException::withMessages(['status' => __('notifications::campaigns.already_sending')]);
        }

        $notificationCampaign->delete();

        return response()->noContent();
    }

    #[Endpoint('Duplicate a campaign', 'Copies it as a new draft.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 201, description: 'The new draft.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    public function duplicate(Request $request, NotificationCampaign $notificationCampaign): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $notificationCampaign);
        Gate::forUser($request->user())->authorize('create', NotificationCampaign::class);

        $copy = $this->campaigns->duplicate($notificationCampaign, $request->user()?->getKey());

        return NotificationCampaignResource::make($copy->refresh()->load(['targets', 'template']))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Send a campaign now', 'Draft or scheduled campaigns; queued, status becomes `sending`.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 202, description: 'Queued for sending.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    #[ApiResponse(['message' => 'This campaign is already being sent or has finished.', 'errors' => ['status' => ['This campaign is already being sent or has finished.']]], 422, 'Already sending, sent or cancelled.')]
    public function send(Request $request, NotificationCampaign $notificationCampaign): JsonResponse
    {
        Gate::forUser($request->user())->authorize('send', $notificationCampaign);

        if (! $this->campaigns->sendNow($notificationCampaign)) {
            throw ValidationException::withMessages(['status' => __('notifications::campaigns.already_sending')]);
        }

        return NotificationCampaignResource::make($notificationCampaign->load(['targets', 'template']))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_ACCEPTED);
    }

    #[Endpoint('Schedule a campaign', 'Sets (or moves) the send time of a draft or scheduled campaign.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 200, description: 'The scheduled campaign.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    #[ApiResponse(['message' => 'Only draft or scheduled campaigns can be changed.', 'errors' => ['status' => ['Only draft or scheduled campaigns can be changed.']]], 422, 'The campaign is no longer editable (only draft or scheduled).')]
    public function schedule(ScheduleNotificationCampaignRequest $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        $campaign = $this->campaigns->schedule($notificationCampaign, Date::parse($request->validated('scheduled_at')));

        return NotificationCampaignResource::make($campaign->load(['targets', 'template']));
    }

    #[Endpoint('Cancel a campaign', 'Draft or scheduled campaigns only.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[ResponseFromApiResource(NotificationCampaignResource::class, NotificationCampaign::class, status: 200, description: 'The cancelled campaign.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    #[ApiResponse(['message' => 'Only draft or scheduled campaigns can be changed.', 'errors' => ['status' => ['Only draft or scheduled campaigns can be changed.']]], 422, 'The campaign is no longer editable (only draft or scheduled).')]
    public function cancel(Request $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        Gate::forUser($request->user())->authorize('update', $notificationCampaign);

        return NotificationCampaignResource::make($this->campaigns->cancel($notificationCampaign)->load(['targets', 'template']));
    }

    #[Endpoint('List WhatsApp deliveries', 'Per-recipient results of a WhatsApp campaign, 25 per page.')]
    #[UrlParam('notification_campaign', 'integer', 'Campaign ID.', example: 15)]
    #[QueryParam('status', 'string', 'Only this result.', required: false, enum: DeliveryStatus::class, example: 'failed')]
    #[QueryParam('per_page', 'integer', 'Items per page. Default 25.', required: false, example: 25)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(NotificationCampaignDeliveryResource::class, NotificationCampaignDelivery::class, status: 200, description: 'Paginated deliveries.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `notification_campaigns.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such campaign.')]
    public function deliveries(Request $request, NotificationCampaign $notificationCampaign): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('view', $notificationCampaign);

        $deliveries = $notificationCampaign->deliveries()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return NotificationCampaignDeliveryResource::collection($deliveries);
    }
}

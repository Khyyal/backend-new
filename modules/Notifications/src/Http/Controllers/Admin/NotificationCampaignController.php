<?php

namespace Modules\Notifications\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Http\Requests\Admin\NotificationCampaignRequest;
use Modules\Notifications\Http\Requests\Admin\ScheduleNotificationCampaignRequest;
use Modules\Notifications\Http\Resources\Admin\NotificationCampaignDeliveryResource;
use Modules\Notifications\Http\Resources\Admin\NotificationCampaignResource;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Services\CampaignService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / Notification campaigns', description: 'Bulk push or WhatsApp campaigns to clients or center staff, sent now or scheduled. Requires `notification_campaigns.manage`.')]
class NotificationCampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
    ) {}

    /**
     * List campaigns.
     */
    #[QueryParameter('channel', description: '`push` or `whatsapp`.', type: 'string')]
    #[QueryParameter('status', description: 'Filter by status.', type: 'string')]
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

    /**
     * Create a campaign.
     *
     * **Push:** `title` and `body` with `en` and `ar`, plus an optional tap `action_*`.
     *
     * **WhatsApp:** `whatsapp_template_id` and `template_parameters`, one binding
     * per template variable in order: `{source: "fixed", value}` or
     * `{source: "recipient", field}`. Client fields: `first_name`, `last_name`,
     * `full_name`, `phone`; center staff fields: `staff_name`, `center_name`, `phone`.
     *
     * Saved as a draft, as scheduled when `scheduled_at` is set, or sent at once with `send_now`.
     */
    #[ApiResponse(status: 201, description: 'The created campaign.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
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

    /**
     * Show a campaign.
     */
    public function show(Request $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        Gate::forUser($request->user())->authorize('view', $notificationCampaign);

        return NotificationCampaignResource::make($notificationCampaign->load(['targets', 'template']));
    }

    /**
     * Update a draft or scheduled campaign.
     */
    #[ApiResponse(status: 422, description: 'Validation failed, or the campaign is no longer editable.')]
    public function update(NotificationCampaignRequest $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        $campaign = $this->campaigns->update($notificationCampaign, $request->validated());

        return NotificationCampaignResource::make($campaign->refresh()->load(['targets', 'template']));
    }

    /**
     * Delete a draft, scheduled or finished campaign.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
    #[ApiResponse(status: 422, description: 'The campaign is being sent.')]
    public function destroy(Request $request, NotificationCampaign $notificationCampaign): Response
    {
        Gate::forUser($request->user())->authorize('delete', $notificationCampaign);

        if ($notificationCampaign->status === CampaignStatus::Sending) {
            throw ValidationException::withMessages(['status' => __('notifications::campaigns.already_sending')]);
        }

        $notificationCampaign->delete();

        return response()->noContent();
    }

    /**
     * Duplicate a campaign as a new draft.
     */
    #[ApiResponse(status: 201, description: 'The new draft.')]
    public function duplicate(Request $request, NotificationCampaign $notificationCampaign): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $notificationCampaign);
        Gate::forUser($request->user())->authorize('create', NotificationCampaign::class);

        $copy = $this->campaigns->duplicate($notificationCampaign, $request->user()?->getKey());

        return NotificationCampaignResource::make($copy->refresh()->load(['targets', 'template']))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Send a draft or scheduled campaign now.
     */
    #[ApiResponse(status: 202, description: 'Queued for sending; status is `sending`.')]
    #[ApiResponse(status: 422, description: 'Already sending, sent or cancelled.')]
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

    /**
     * Schedule a campaign.
     */
    public function schedule(ScheduleNotificationCampaignRequest $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        $campaign = $this->campaigns->schedule($notificationCampaign, Date::parse($request->validated('scheduled_at')));

        return NotificationCampaignResource::make($campaign->load(['targets', 'template']));
    }

    /**
     * Cancel a draft or scheduled campaign.
     */
    public function cancel(Request $request, NotificationCampaign $notificationCampaign): NotificationCampaignResource
    {
        Gate::forUser($request->user())->authorize('update', $notificationCampaign);

        return NotificationCampaignResource::make($this->campaigns->cancel($notificationCampaign)->load(['targets', 'template']));
    }

    /**
     * List WhatsApp deliveries of a campaign.
     */
    #[QueryParameter('status', description: '`sent` or `failed`.', type: 'string')]
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

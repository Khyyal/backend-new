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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Http\Requests\Admin\WhatsAppTemplateRequest;
use Modules\Notifications\Http\Resources\Admin\WhatsAppTemplateResource;
use Modules\Notifications\Models\WhatsAppTemplate;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / WhatsApp templates', description: 'Templates approved in Voxa / Meta, registered so WhatsApp campaigns can fill their variables. Requires `whatsapp_templates.manage` (listing also allowed with `notification_campaigns.manage`).')]
class WhatsAppTemplateController extends Controller
{
    /**
     * List templates.
     */
    #[QueryParameter('active', description: 'Only active templates when `true`.', type: 'boolean')]
    #[ApiResponse(status: 403, description: 'Missing permission.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', WhatsAppTemplate::class);

        $templates = WhatsAppTemplate::query()
            ->when($request->boolean('active'), fn ($query) => $query->where('is_active', true))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return WhatsAppTemplateResource::collection($templates);
    }

    /**
     * Register a template.
     *
     * `provider_name` and `language` must match the template approved in Voxa.
     * `variables` lists the placeholders in order (`{{1}}`, `{{2}}` …), each
     * with a `label` and an optional default binding used to prefill campaigns.
     */
    #[ApiResponse(status: 201, description: 'The created template.')]
    #[ApiResponse(status: 422, description: 'Validation failed.')]
    public function store(WhatsAppTemplateRequest $request): JsonResponse
    {
        $template = WhatsAppTemplate::create($request->validated());

        return WhatsAppTemplateResource::make($template)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a template.
     */
    public function show(Request $request, WhatsAppTemplate $whatsappTemplate): WhatsAppTemplateResource
    {
        Gate::forUser($request->user())->authorize('view', $whatsappTemplate);

        return WhatsAppTemplateResource::make($whatsappTemplate);
    }

    /**
     * Update a template.
     */
    public function update(WhatsAppTemplateRequest $request, WhatsAppTemplate $whatsappTemplate): WhatsAppTemplateResource
    {
        $whatsappTemplate->update($request->validated());

        return WhatsAppTemplateResource::make($whatsappTemplate);
    }

    /**
     * Delete a template.
     *
     * Refused while a draft, scheduled or sending campaign uses it.
     */
    #[ApiResponse(status: 204, description: 'Deleted.')]
    #[ApiResponse(status: 422, description: 'The template is used by an unfinished campaign.')]
    public function destroy(Request $request, WhatsAppTemplate $whatsappTemplate): Response
    {
        Gate::forUser($request->user())->authorize('delete', $whatsappTemplate);

        $inUse = $whatsappTemplate->campaigns()
            ->whereIn('status', [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value, CampaignStatus::Sending->value])
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['template' => __('notifications::campaigns.template_in_use')]);
        }

        $whatsappTemplate->delete();

        return response()->noContent();
    }
}

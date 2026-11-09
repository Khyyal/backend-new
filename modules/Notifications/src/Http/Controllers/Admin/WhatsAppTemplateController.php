<?php

namespace Modules\Notifications\Http\Controllers\Admin;

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
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Http\Requests\Admin\WhatsAppTemplateRequest;
use Modules\Notifications\Http\Resources\Admin\WhatsAppTemplateResource;
use Modules\Notifications\Models\WhatsAppTemplate;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / WhatsApp templates', 'Templates approved in Voxa / Meta, registered so WhatsApp campaigns can fill their variables. Requires `whatsapp_templates.manage` (listing is also allowed with `notification_campaigns.manage`).')]
class WhatsAppTemplateController extends Controller
{
    #[Endpoint('List templates', 'Paginated.')]
    #[QueryParam('active', 'boolean', 'Only active templates when true.', required: false, example: true)]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Items per page, 1-100. Default 15.', required: false, example: 15)]
    #[ResponseFromApiResource(WhatsAppTemplateResource::class, WhatsAppTemplate::class, status: 200, description: 'Paginated templates.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `whatsapp_templates.manage` and `notification_campaigns.manage`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', WhatsAppTemplate::class);

        $templates = WhatsAppTemplate::query()
            ->when($request->boolean('active'), fn ($query) => $query->where('is_active', true))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return WhatsAppTemplateResource::collection($templates);
    }

    #[Endpoint('Register a template', '`provider_name` and `language` must match the template approved in Voxa.')]
    #[ResponseFromApiResource(WhatsAppTemplateResource::class, WhatsAppTemplate::class, status: 201, description: 'The created template.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `whatsapp_templates.manage`.')]
    #[ApiResponse(['message' => 'The provider name has already been taken.', 'errors' => ['provider_name' => ['The provider name has already been taken.']]], 422, 'Validation failed.')]
    public function store(WhatsAppTemplateRequest $request): JsonResponse
    {
        $template = WhatsAppTemplate::create($request->validated());

        return WhatsAppTemplateResource::make($template)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a template')]
    #[UrlParam('whatsapp_template', 'integer', 'Template ID.', example: 4)]
    #[ResponseFromApiResource(WhatsAppTemplateResource::class, WhatsAppTemplate::class, status: 200, description: 'The template.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `whatsapp_templates.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such template.')]
    public function show(Request $request, WhatsAppTemplate $whatsappTemplate): WhatsAppTemplateResource
    {
        Gate::forUser($request->user())->authorize('view', $whatsappTemplate);

        return WhatsAppTemplateResource::make($whatsappTemplate);
    }

    #[Endpoint('Update a template', 'Replaces the definition (same body as create).')]
    #[UrlParam('whatsapp_template', 'integer', 'Template ID.', example: 4)]
    #[ResponseFromApiResource(WhatsAppTemplateResource::class, WhatsAppTemplate::class, status: 200, description: 'The updated template.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `whatsapp_templates.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such template.')]
    #[ApiResponse(['message' => 'The variables.0.default_field field is required when variables.0.default_source is recipient.', 'errors' => ['variables.0.default_field' => ['The variables.0.default_field field is required when variables.0.default_source is recipient.']]], 422, 'Validation failed.')]
    public function update(WhatsAppTemplateRequest $request, WhatsAppTemplate $whatsappTemplate): WhatsAppTemplateResource
    {
        $whatsappTemplate->update($request->validated());

        return WhatsAppTemplateResource::make($whatsappTemplate);
    }

    #[Endpoint('Delete a template', 'Refused while a draft, scheduled or sending campaign uses it.')]
    #[UrlParam('whatsapp_template', 'integer', 'Template ID.', example: 4)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `whatsapp_templates.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such template.')]
    #[ApiResponse(['message' => 'This template is used by a campaign that has not finished yet.', 'errors' => ['template' => ['This template is used by a campaign that has not finished yet.']]], 422, 'Used by an unfinished campaign.')]
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

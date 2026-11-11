<?php

namespace Modules\Settlements\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Modules\Settlements\Http\Requests\Admin\PreviewPayoutInvoiceRequest;
use Modules\Settlements\Http\Requests\Admin\UpdatePayoutInvoiceTemplateRequest;
use Modules\Settlements\Http\Resources\PayoutInvoiceTemplateResource;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Models\PayoutInvoiceTemplate;
use Modules\Settlements\Services\PayoutInvoiceRenderer;
use Modules\Settlements\Services\PayoutInvoiceTemplateService;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Services\LegalHtmlSanitizer;

#[Group('Admin / Payout invoice template', 'The one layout every printed payout invoice uses: company details, logo, accent color, notes and which sections are shown. The figures always come from the payout. Requires `payouts.manage`.')]
class PayoutInvoiceTemplateController extends Controller
{
    public function __construct(private readonly PayoutInvoiceTemplateService $templates) {}

    #[Endpoint('Show the invoice template')]
    #[ResponseFromApiResource(PayoutInvoiceTemplateResource::class, PayoutInvoiceTemplate::class, status: 200, description: 'The template.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    public function show(Request $request): PayoutInvoiceTemplateResource
    {
        Gate::forUser($request->user())->authorize('manageInvoiceTemplate', CenterPayout::class);

        return PayoutInvoiceTemplateResource::make(PayoutInvoiceTemplate::current()->load('media'));
    }

    #[Endpoint('Update the invoice template', 'Replaces every field. Notes are sanitized like legal documents (scripts, event handlers and styles other than text alignment are removed).')]
    #[ResponseFromApiResource(PayoutInvoiceTemplateResource::class, PayoutInvoiceTemplate::class, status: 200, description: 'The updated template.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(['message' => 'The accent color field format is invalid.', 'errors' => ['accent_color' => ['The accent color field format is invalid.']]], 422, 'Validation failed, or the logo is not a usable JPEG/PNG upload.')]
    public function update(UpdatePayoutInvoiceTemplateRequest $request, LegalHtmlSanitizer $sanitizer): PayoutInvoiceTemplateResource
    {
        $template = $this->templates->update(
            PayoutInvoiceTemplate::current(),
            $request->templateAttributes($sanitizer),
            $request->validated(),
            $request->user(),
        );

        return PayoutInvoiceTemplateResource::make($template);
    }

    #[Endpoint('Preview the invoice template', 'Renders a PDF from the unsaved form (same body as the update) without saving anything, using a payout or sample data.')]
    #[ApiResponse('<PDF file>', 200, 'The preview (`application/pdf`).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(['message' => 'The selected locale is invalid.', 'errors' => ['locale' => ['The selected locale is invalid.']]], 422, 'Validation failed.')]
    public function preview(PreviewPayoutInvoiceRequest $request, LegalHtmlSanitizer $sanitizer, PayoutInvoiceRenderer $renderer): Response
    {
        $saved = PayoutInvoiceTemplate::current();
        $logo = $this->templates->previewLogo($saved, $request->validated(), $request->user());

        // An unsaved copy: the stored template is never changed by a preview.
        $draft = $saved->replicate()->applyAttributes($request->templateAttributes($sanitizer));

        $payoutId = $request->validated('payout_id');
        $payout = $payoutId !== null
            ? CenterPayout::query()->with(['center', 'entries'])->findOrFail($payoutId)
            : $renderer->samplePayout($request->validated('locale'));

        $pdf = $renderer->render($payout, $draft, $request->validated('locale'), $logo ?? ($saved->getFirstMedia('logo') ?? false));

        return PayoutInvoiceController::pdf($pdf, 'payout-invoice-preview.pdf');
    }
}

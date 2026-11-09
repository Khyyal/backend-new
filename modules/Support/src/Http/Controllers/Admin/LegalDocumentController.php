<?php

namespace Modules\Support\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Enums\LegalAudience;
use Modules\Support\Enums\LegalDocumentType;
use Modules\Support\Http\Requests\Admin\UpdateLegalDocumentRequest;
use Modules\Support\Http\Resources\Admin\LegalDocumentResource;
use Modules\Support\Models\LegalDocument;
use Modules\Support\Services\LegalHtmlSanitizer;

#[Group('Admin / Legal documents', 'Privacy policy and terms pages of the client and center apps, as HTML in en and ar. Requires `legal_documents.manage`.')]
class LegalDocumentController extends Controller
{
    #[Endpoint('List legal documents', 'All four documents (client and center, privacy policy and terms). Not paginated.')]
    #[ResponseFromApiResource(LegalDocumentResource::class, LegalDocument::class, status: 200, description: 'The documents.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `legal_documents.manage`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', LegalDocument::class);

        return LegalDocumentResource::collection(LegalDocument::query()->ordered()->get());
    }

    #[Endpoint('Show a legal document')]
    #[UrlParam('audience', 'string', 'Which app.', enum: ['client', 'center'], example: 'client')]
    #[UrlParam('type', 'string', 'Which page.', enum: ['privacy_policy', 'terms'], example: 'privacy_policy')]
    #[ResponseFromApiResource(LegalDocumentResource::class, LegalDocument::class, status: 200, description: 'The document.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `legal_documents.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Unknown audience or type.')]
    public function show(Request $request, LegalAudience $audience, LegalDocumentType $type): LegalDocumentResource
    {
        $document = LegalDocument::findFor($audience, $type);

        Gate::forUser($request->user())->authorize('view', $document);

        return LegalDocumentResource::make($document);
    }

    #[Endpoint('Update a legal document', 'Replaces both locales. The HTML is sanitized before saving: scripts, event handlers, inline styles other than text alignment, and non-http(s)/mailto/tel links are removed.')]
    #[UrlParam('audience', 'string', 'Which app.', enum: ['client', 'center'], example: 'client')]
    #[UrlParam('type', 'string', 'Which page.', enum: ['privacy_policy', 'terms'], example: 'privacy_policy')]
    #[ResponseFromApiResource(LegalDocumentResource::class, LegalDocument::class, status: 200, description: 'The updated document.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `legal_documents.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Unknown audience or type.')]
    #[ApiResponse(['message' => 'The content.ar field is required.', 'errors' => ['content.ar' => ['The content.ar field is required.']]], 422, 'Validation failed.')]
    public function update(UpdateLegalDocumentRequest $request, LegalAudience $audience, LegalDocumentType $type, LegalHtmlSanitizer $sanitizer): LegalDocumentResource
    {
        $document = LegalDocument::findFor($audience, $type);
        $document->update(['content' => $request->sanitizedContent($sanitizer)]);

        return LegalDocumentResource::make($document);
    }
}

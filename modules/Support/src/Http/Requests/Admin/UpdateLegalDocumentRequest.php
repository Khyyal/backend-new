<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Enums\LegalAudience;
use Modules\Support\Enums\LegalDocumentType;
use Modules\Support\Models\LegalDocument;
use Modules\Support\Services\LegalHtmlSanitizer;

#[BodyParam('content', 'object', 'Page HTML; both locales are required.', example: ['en' => '<h2>Privacy</h2><p>We respect your privacy.</p>', 'ar' => '<h2>الخصوصية</h2><p>نحترم خصوصيتك.</p>'])]
#[BodyParam('content.en', 'string', 'English HTML (max 200,000 characters). Scripts, event handlers and unsafe links are removed.', example: '<h2>Privacy</h2><p>We respect your privacy.</p>')]
#[BodyParam('content.ar', 'string', 'Arabic HTML (max 200,000 characters). Scripts, event handlers and unsafe links are removed.', example: '<h2>الخصوصية</h2><p>نحترم خصوصيتك.</p>')]
class UpdateLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->legalDocument());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'array:en,ar'],
            'content.en' => ['required', 'string', 'max:'.LegalHtmlSanitizer::MAX_LENGTH],
            'content.ar' => ['required', 'string', 'max:'.LegalHtmlSanitizer::MAX_LENGTH],
        ];
    }

    public function legalDocument(): LegalDocument
    {
        /** @var LegalAudience $audience */
        $audience = $this->route('audience');
        /** @var LegalDocumentType $type */
        $type = $this->route('type');

        return LegalDocument::findFor($audience, $type);
    }

    /**
     * The validated content with each locale's HTML sanitized.
     *
     * @return array{en: string, ar: string}
     */
    public function sanitizedContent(LegalHtmlSanitizer $sanitizer): array
    {
        return [
            'en' => $sanitizer->sanitize($this->validated('content.en')),
            'ar' => $sanitizer->sanitize($this->validated('content.ar')),
        ];
    }
}

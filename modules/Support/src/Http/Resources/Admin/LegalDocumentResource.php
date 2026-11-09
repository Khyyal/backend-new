<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Models\LegalDocument;

/**
 * @property LegalDocument $resource
 */
class LegalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('audience', 'string', 'Which app the page is for.', enum: ['client', 'center'], example: 'client')]
    #[ResponseField('type', 'string', 'Which page.', enum: ['privacy_policy', 'terms'], example: 'privacy_policy')]
    #[ResponseField('content', 'object', 'Sanitized HTML per locale; empty strings until first saved.', example: ['en' => '<h2>Privacy</h2><p>We respect your privacy.</p>', 'ar' => '<h2>الخصوصية</h2><p>نحترم خصوصيتك.</p>'])]
    #[ResponseField('url', 'object', 'Public web page per locale.', example: ['en' => 'https://api.khyyal.com/legal/client/privacy_policy?lang=en', 'ar' => 'https://api.khyyal.com/legal/client/privacy_policy?lang=ar'])]
    #[ResponseField('updated_at', 'string', 'Last change (ISO-8601).', example: '2026-11-09T10:00:00+00:00')]
    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'audience' => $document->audience->value,
            'type' => $document->type->value,
            'content' => collect(LegalDocument::LOCALES)
                ->mapWithKeys(fn (string $locale): array => [$locale => (string) $document->getTranslation('content', $locale, false)])
                ->all(),
            'url' => collect(LegalDocument::LOCALES)
                ->mapWithKeys(fn (string $locale): array => [$locale => $document->url($locale)])
                ->all(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }
}

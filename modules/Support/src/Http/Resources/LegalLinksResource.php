<?php

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Enums\LegalAudience;
use Modules\Support\Enums\LegalDocumentType;

/**
 * The legal page URLs of one audience, in the request locale.
 *
 * @property LegalAudience $resource
 */
class LegalLinksResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    #[ResponseField('privacy_policy_url', 'string', 'Privacy policy web page in the request locale; open it in a webview.', example: 'https://api.khyyal.com/legal/client/privacy_policy?lang=en')]
    #[ResponseField('terms_url', 'string', 'Terms and conditions web page in the request locale; open it in a webview.', example: 'https://api.khyyal.com/legal/client/terms?lang=en')]
    public function toArray(Request $request): array
    {
        $locale = in_array(app()->getLocale(), ['en', 'ar'], true) ? app()->getLocale() : 'en';

        return [
            'privacy_policy_url' => $this->url(LegalDocumentType::PrivacyPolicy, $locale),
            'terms_url' => $this->url(LegalDocumentType::Terms, $locale),
        ];
    }

    private function url(LegalDocumentType $type, string $locale): string
    {
        return route('legal.show', ['audience' => $this->resource, 'type' => $type, 'lang' => $locale]);
    }
}

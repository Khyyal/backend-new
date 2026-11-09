<?php

namespace Modules\Support\Enums;

enum LegalDocumentType: string
{
    case PrivacyPolicy = 'privacy_policy';
    case Terms = 'terms';

    /**
     * Page title in the given locale.
     */
    public function title(string $locale): string
    {
        return __('support::legal.titles.'.$this->value, locale: $locale);
    }
}

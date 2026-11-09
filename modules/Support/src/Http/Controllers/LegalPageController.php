<?php

namespace Modules\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Support\Enums\LegalAudience;
use Modules\Support\Enums\LegalDocumentType;
use Modules\Support\Models\LegalDocument;

/**
 * Renders a legal document as a standalone HTML page for the apps' webviews.
 */
class LegalPageController extends Controller
{
    public function __invoke(Request $request, LegalAudience $audience, LegalDocumentType $type): Response
    {
        $locale = in_array($request->query('lang'), LegalDocument::LOCALES, true) ? $request->query('lang') : 'en';
        $document = LegalDocument::findFor($audience, $type);

        return response()
            ->view('support::legal.show', [
                'locale' => $locale,
                'title' => $type->title($locale),
                'content' => $document->getTranslation('content', $locale, false),
            ])
            ->header('Cache-Control', 'public, max-age=300');
    }
}

<?php

namespace Modules\Settlements\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Settlements\Models\CenterPayout;
use Modules\Settlements\Models\PayoutInvoiceTemplate;
use Modules\Settlements\Services\PayoutInvoiceRenderer;
use Modules\Support\Documentation\ApiExamples;

#[Group('Admin / Payouts', 'Weekly payouts of what the platform owes centers. Every Sunday 00:00 UTC a pending payout is created per center for its ledger entries past the hold period; finance transfers it and marks it paid. Amounts are halalas. Requires `payouts.manage`.')]
class PayoutInvoiceController extends Controller
{
    #[Endpoint('Print a payout invoice', 'A PDF of the payout laid out by the invoice template, in Arabic (right-to-left) or English. Any status; cancelled payouts carry a watermark.')]
    #[UrlParam('payout', 'integer', 'Payout ID.', example: 17)]
    #[QueryParam('locale', 'string', 'Invoice language. Default `ar`.', required: false, enum: ['en', 'ar'], example: 'ar')]
    #[ApiResponse('<PDF file>', 200, 'The invoice (`application/pdf`, shown inline).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `payouts.manage`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such payout.')]
    #[ApiResponse(['message' => 'The selected locale is invalid.', 'errors' => ['locale' => ['The selected locale is invalid.']]], 422, 'Unknown locale.')]
    public function show(Request $request, CenterPayout $payout, PayoutInvoiceRenderer $renderer): Response
    {
        Gate::forUser($request->user())->authorize('view', $payout);

        $locale = $request->validate(['locale' => ['sometimes', 'string', 'in:en,ar']])['locale'] ?? 'ar';

        $pdf = $renderer->render($payout->load(['center', 'entries']), PayoutInvoiceTemplate::current(), $locale);

        return self::pdf($pdf, 'payout-invoice-'.$payout->id.'-'.$locale.'.pdf');
    }

    public static function pdf(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}

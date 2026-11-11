<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Clients\Models\Client;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Http\Resources\ClientPurchaseResource;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Purchases', 'What you bought from centers.')]
class PurchaseController extends Controller
{
    #[Endpoint('Show my purchase by code', 'Looks one of your purchases up by its public 6-character `code` (case-insensitive), with its line items.')]
    #[UrlParam('code', 'string', 'Purchase code.', example: 'K7QX2M')]
    #[ResponseFromApiResource(ClientPurchaseResource::class, Purchase::class, status: 200, description: 'The purchase.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No purchase of yours with this code.')]
    public function showByCode(Request $request, string $code): ClientPurchaseResource
    {
        /** @var Client $client */
        $client = $request->user('client');

        $purchase = Purchase::query()
            ->where('buyer_type', $client->getMorphClass())
            ->where('buyer_id', $client->getKey())
            ->whereCode($code)
            ->with(['merchant', 'items', 'latestPayment'])
            ->firstOrFail();

        return ClientPurchaseResource::make($purchase);
    }
}

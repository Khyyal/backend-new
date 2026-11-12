<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Clients\Models\Client;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Http\Resources\ClientPurchaseResource;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Purchases', 'What you bought from centers.')]
class PurchaseController extends Controller
{
    #[Endpoint('List my purchases', 'Newest first, 15 per page, with line items.')]
    #[QueryParam('status', 'string', 'Only this status.', required: false, enum: PurchaseStatus::class, example: 'confirmed')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(ClientPurchaseResource::class, Purchase::class, status: 200, description: 'Paginated purchases.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The selected status is invalid.', 'errors' => ['status' => ['The selected status is invalid.']]], 422, 'Validation failed.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PurchaseStatus::class)],
        ]);

        $purchases = $this->purchases($request)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return ClientPurchaseResource::collection($purchases);
    }

    #[Endpoint('Show my purchase', 'One of your purchases by its ID, with its line items.')]
    #[UrlParam('purchase', 'integer', 'Purchase ID.', example: 5521)]
    #[ResponseFromApiResource(ClientPurchaseResource::class, Purchase::class, status: 200, description: 'The purchase.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such purchase of yours.')]
    public function show(Request $request, int $purchase): ClientPurchaseResource
    {
        return ClientPurchaseResource::make($this->purchases($request)->findOrFail($purchase));
    }

    #[Endpoint('Show my purchase by code', 'Looks one of your purchases up by its public 6-character `code` (case-insensitive), with its line items.')]
    #[UrlParam('code', 'string', 'Purchase code.', example: 'K7QX2M')]
    #[ResponseFromApiResource(ClientPurchaseResource::class, Purchase::class, status: 200, description: 'The purchase.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No purchase of yours with this code.')]
    public function showByCode(Request $request, string $code): ClientPurchaseResource
    {
        return ClientPurchaseResource::make($this->purchases($request)->whereCode($code)->firstOrFail());
    }

    /**
     * The authenticated client's purchases, with what `ClientPurchaseResource` renders.
     *
     * @return Builder<Purchase>
     */
    private function purchases(Request $request): Builder
    {
        /** @var Client $client */
        $client = $request->user('client');

        return Purchase::query()
            ->where('buyer_type', $client->getMorphClass())
            ->where('buyer_id', $client->getKey())
            ->with(['merchant', 'items', 'latestPayment']);
    }
}

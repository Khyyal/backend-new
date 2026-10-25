<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Resources\Center\PurchaseDetailsResource;
use Modules\Centers\Http\Resources\Center\PurchaseResource;
use Modules\Centers\Models\Center;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;

#[Group(name: 'Center / Purchases', description: 'What clients bought from the center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class PurchaseController extends Controller
{
    /**
     * List the center's purchases.
     *
     * Paginated (25 per page), newest first.
     *
     * **Query:** `status`, optional: `pending`, `confirmed`, `cancelled` or `completed`.
     */
    #[Response(status: 200, description: 'Paginated `PurchaseResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s purchases.')]
    #[Response(status: 422, description: 'Unknown status.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        $this->authorize($request, $center);

        $validated = $request->validate(['status' => ['sometimes', 'string', 'in:'.implode(',', array_column(PurchaseStatus::cases(), 'value'))]]);

        $purchases = $this->purchases($center)
            ->when(isset($validated['status']), fn (Builder $query) => $query->where('status', $validated['status']))
            ->with('buyer')
            ->withCount('items')
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return PurchaseResource::collection($purchases);
    }

    /**
     * Show a purchase.
     *
     * With its line items and payments.
     */
    #[Response(status: 200, description: 'The `PurchaseDetailsResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s purchases.')]
    #[Response(status: 404, description: 'No such purchase in this center.')]
    public function show(Request $request, Center $center, int $purchase): PurchaseDetailsResource
    {
        $this->authorize($request, $center);

        return PurchaseDetailsResource::make(
            $this->purchases($center)->with(['buyer', 'items', 'payments'])->withCount('items')->findOrFail($purchase)
        );
    }

    private function authorize(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('manageReservations', $center);
    }

    /**
     * @return Builder<Purchase>
     */
    private function purchases(Center $center): Builder
    {
        return Purchase::query()
            ->where('merchant_type', $center->getMorphClass())
            ->where('merchant_id', $center->getKey());
    }
}

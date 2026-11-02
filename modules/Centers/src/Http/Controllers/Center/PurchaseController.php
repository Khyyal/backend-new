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
use Modules\Centers\Services\CenterPurchaseVisibility;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\Purchase;

#[Group(name: 'Center / Purchases', description: 'What clients bought from the center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class PurchaseController extends Controller
{
    public function __construct(private readonly CenterPurchaseVisibility $visibility) {}

    /**
     * List the center's purchases.
     *
     * Paginated (25 per page), newest first. Non-primary users only see
     * purchases for the service types they may view, narrowed by trainer
     * (own trainer, other trainers, unassigned); amounts are omitted without
     * `purchases.view_prices`.
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

        $purchases = $this->purchases($request, $center)
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
     * With its line items and payments (amounts and payments omitted without
     * `purchases.view_prices`). Purchases the user may not see return 404.
     */
    #[Response(status: 200, description: 'The `PurchaseDetailsResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s purchases.')]
    #[Response(status: 404, description: 'No such purchase in this center.')]
    public function show(Request $request, Center $center, int $purchase): PurchaseDetailsResource
    {
        $this->authorize($request, $center);

        return PurchaseDetailsResource::make(
            $this->purchases($request, $center)->with(['buyer', 'items', 'payments'])->withCount('items')->findOrFail($purchase)
        );
    }

    private function authorize(Request $request, Center $center): void
    {
        Gate::forUser($request->user())->authorize('viewPurchases', $center);
    }

    /**
     * The center's purchases the user may see (see {@see CenterPurchaseVisibility}).
     *
     * @return Builder<Purchase>
     */
    private function purchases(Request $request, Center $center): Builder
    {
        $query = Purchase::query()
            ->where('merchant_type', $center->getMorphClass())
            ->where('merchant_id', $center->getKey());

        return $this->visibility->apply($query, $request->user(), $center);
    }
}

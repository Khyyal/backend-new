<?php

namespace Modules\Clients\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Centers\Models\Center;
use Modules\Clients\Exports\CenterClientsExport;
use Modules\Clients\Http\Requests\Center\ExportClientsRequest;
use Modules\Clients\Http\Requests\Center\ListClientsRequest;
use Modules\Clients\Http\Resources\CenterClientResource;
use Modules\Clients\Services\CenterClientQuery;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Services\ExportService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Center / Clients', description: 'The clients who bought from the center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ClientController extends Controller
{
    /**
     * List the center's clients.
     *
     * Clients that belong to this center (registered from its page or created by it) or have at least one
     * confirmed or completed purchase in it. Paginated (25 per page), most recent buyer first, then clients
     * without purchases (newest first). Pending and cancelled purchases are ignored.
     */
    #[Response(status: 200, description: 'Paginated collection of `{id, full_name, phone_number, first_purchase_at, last_purchase_at, purchases_count}`. Purchase dates are `null` for clients without purchases.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'No access to this center\'s reservations.')]
    public function index(ListClientsRequest $request, Center $center, CenterClientQuery $query): AnonymousResourceCollection
    {
        $clients = $query->build($center, $request->validated())
            ->paginate(25)
            ->withQueryString();

        return CenterClientResource::collection($clients);
    }

    /**
     * Export the center's clients.
     *
     * Queues an `.xlsx` export of the center's clients with their purchase stats, with the same filters as
     * the list (`q`, `from`/`to` on the last purchase date). Requires `clients.export`. Download it from
     * `GET /centers/{center}/exports/{export}/download` once ready.
     */
    #[Response(status: 202, description: 'The queued `ExportResource`; the requester is notified (`export_ready` / `export_failed`) when it finishes.')]
    #[Response(status: 403, description: 'No permission to export this center\'s clients.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    #[Response(status: 429, description: 'An export of this kind is already being generated.')]
    public function export(ExportClientsRequest $request, Center $center, ExportService $exports): JsonResponse
    {
        $export = $exports->start(
            $request->user(),
            ExportType::CenterClients,
            new CenterClientsExport($request->validated(), $center->id),
            $center->id,
        );

        return ExportResource::make($export)->response()->setStatusCode(HttpStatus::HTTP_ACCEPTED);
    }
}

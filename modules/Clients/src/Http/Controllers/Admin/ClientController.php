<?php

namespace Modules\Clients\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Clients\Exports\AdminClientsExport;
use Modules\Clients\Http\Requests\Admin\ExportClientsRequest;
use Modules\Clients\Http\Requests\Admin\ListClientsRequest;
use Modules\Clients\Http\Resources\Admin\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\AdminClientQuery;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Services\ExportService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Admin / Clients', description: 'System user view of every client across all centers. Requires a system user Bearer token with the matching `clients.*` permission.')]
class ClientController extends Controller
{
    /**
     * List all clients.
     *
     * Global list across every center (not scoped to one center). Paginated (15 per page, `per_page` up to 100),
     * newest first. Soft-deleted clients are excluded.
     */
    #[Response(status: 200, description: 'Paginated `ClientResource` collection with `centers_count`, `reservations_count`, `packages_count` and `memberships_count`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'Missing the `clients.view_any` permission.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    public function index(ListClientsRequest $request, AdminClientQuery $query): AnonymousResourceCollection
    {
        $clients = $query->build($request->validated())
            ->paginate(min(100, max(1, $request->integer('per_page', 15))))
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    /**
     * Search clients.
     *
     * Lightweight lookup for pickers (e.g. campaign targets): `q` matches the full name or the phone number
     * (every word must match). Not paginated, at most `limit` results (default 20, max 50), newest first.
     */
    #[QueryParameter('q', description: 'Search term, at least 2 characters.', type: 'string', required: true)]
    #[QueryParameter('limit', description: 'Maximum results (1-50, default 20).', type: 'integer')]
    #[Response(status: 200, description: '`{data: [{id, full_name, phone_number}]}`.')]
    #[Response(status: 403, description: 'Missing the `clients.view_any` permission.')]
    #[Response(status: 422, description: '`q` is missing or shorter than 2 characters.')]
    public function search(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Client::class);

        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $clients = Client::query()
            ->search($request->string('q')->toString())
            ->orderByDesc('id')
            ->limit(min(50, max(1, $request->integer('limit', 20))))
            ->get(['id', 'first_name', 'last_name', 'phone_number']);

        return response()->json(['data' => $clients->map(fn (Client $client): array => [
            'id' => $client->id,
            'full_name' => $client->full_name,
            'phone_number' => $client->phone_number,
        ])->all()]);
    }

    /**
     * Show a client.
     */
    #[Response(status: 200, description: 'The `ClientResource` with city and activity counts.')]
    #[Response(status: 403, description: 'Missing the `clients.view` permission.')]
    #[Response(status: 404, description: 'Client not found.')]
    public function show(Request $request, Client $client): ClientResource
    {
        Gate::forUser($request->user())->authorize('view', $client);

        return ClientResource::make($client->load('city')->loadCount(['centers', 'reservations', 'packages', 'memberships']));
    }

    /**
     * Export clients.
     *
     * Queues an `.xlsx` export of every client matching the same filters as the list (`search`, `city_id`,
     * `gender`, `from`/`to` on the registration date). Download it from
     * `GET /admin/exports/{export}/download` once ready.
     */
    #[Response(status: 202, description: 'The queued `ExportResource`; the requester is notified (`export_ready` / `export_failed`) when it finishes.')]
    #[Response(status: 403, description: 'Missing the `clients.export` permission.')]
    #[Response(status: 422, description: 'Invalid filters.')]
    #[Response(status: 429, description: 'An export of this kind is already being generated.')]
    public function export(ExportClientsRequest $request, ExportService $exports): JsonResponse
    {
        $export = $exports->start($request->user(), ExportType::AdminClients, new AdminClientsExport($request->validated()));

        return ExportResource::make($export)->response()->setStatusCode(HttpStatus::HTTP_ACCEPTED);
    }
}

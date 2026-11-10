<?php

namespace Modules\Clients\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Clients\Exports\AdminClientsExport;
use Modules\Clients\Http\Requests\Admin\ExportClientsRequest;
use Modules\Clients\Http\Requests\Admin\ListClientsRequest;
use Modules\Clients\Http\Resources\Admin\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\AdminClientQuery;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Admin / Clients', 'Every client across all centers. Requires the matching `clients.*` permission.')]
class ClientController extends Controller
{
    #[Endpoint('List clients', 'All centers, newest first unless `sort` is given, paginated, with activity counts. Soft-deleted clients are excluded.')]
    #[ResponseFromApiResource(ClientResource::class, Client::class, status: 200, description: 'Paginated clients.', paginate: 15, with: ['city'], withCount: ['centers', 'reservations', 'packages', 'memberships'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `clients.view_any`.')]
    #[ApiResponse(['message' => 'The selected gender is invalid.', 'errors' => ['gender' => ['The selected gender is invalid.']]], 422, 'Invalid filters.')]
    public function index(ListClientsRequest $request, AdminClientQuery $query): AnonymousResourceCollection
    {
        $clients = $query->build($request->validated())
            ->paginate(min(100, max(1, $request->integer('per_page', 15))))
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    #[Endpoint('Search clients', 'Lightweight lookup for pickers (e.g. campaign targets): `q` matches the full name or phone, every word must match. Newest first, not paginated.')]
    #[QueryParam('q', 'string', 'Search term, 2-100 characters.', required: true, example: 'Noura')]
    #[QueryParam('limit', 'integer', 'Maximum results, 1-50. Default 20.', required: false, example: 20)]
    #[ApiResponse(['data' => [['id' => 77, 'full_name' => 'Noura Al-Qahtani', 'phone_number' => '+966500000003']]], 200, 'Matching clients.')]
    #[ResponseField('data[].id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('data[].full_name', 'string', 'Full name.', example: 'Noura Al-Qahtani')]
    #[ResponseField('data[].phone_number', 'string', 'Phone.', example: '+966500000003')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `clients.view_any`.')]
    #[ApiResponse(['message' => 'The q field must be at least 2 characters.', 'errors' => ['q' => ['The q field must be at least 2 characters.']]], 422, '`q` missing or shorter than 2 characters.')]
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

    #[Endpoint('Show a client', 'With city and activity counts.')]
    #[UrlParam('client', 'integer', 'Client ID.', example: 77)]
    #[ResponseFromApiResource(ClientResource::class, Client::class, status: 200, description: 'The client.', with: ['city'], withCount: ['centers', 'reservations', 'packages', 'memberships'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `clients.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'Client not found.')]
    public function show(Request $request, Client $client): ClientResource
    {
        Gate::forUser($request->user())->authorize('view', $client);

        return ClientResource::make($client->load('city')->loadCount(['centers', 'reservations', 'packages', 'memberships']));
    }

    #[Endpoint('Export clients', 'Queues an `.xlsx` export of the clients matching the list filters.')]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 202, description: 'The queued export; download it from `GET /api/v1/admin/exports/{export}/download` once `completed`. The requester gets an `export_ready` or `export_failed` notification.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `clients.export`.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    #[ApiResponse(['message' => 'An export of this kind is already being generated. You will be notified when it is ready.'], 429, 'An export of this kind is already being generated.')]
    public function export(ExportClientsRequest $request, ExportService $exports): JsonResponse
    {
        $export = $exports->start($request->user(), ExportType::AdminClients, new AdminClientsExport($request->validated()));

        return ExportResource::make($export)->response()->setStatusCode(HttpStatus::HTTP_ACCEPTED);
    }
}

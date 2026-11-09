<?php

namespace Modules\Clients\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Clients\Exports\CenterClientsExport;
use Modules\Clients\Http\Requests\Center\ExportClientsRequest;
use Modules\Clients\Http\Requests\Center\ListClientsRequest;
use Modules\Clients\Http\Resources\CenterClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\CenterClientQuery;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Models\Export;
use Modules\Exports\Services\ExportService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Clients', 'The clients of a center. Served on `centers/{center}/clients` (center-scoped token) and `admin/centers/{center}/clients` (system user).')]
class ClientController extends Controller
{
    #[Endpoint('List the center\'s clients', 'Clients who belong to the center (registered from its page or created by it) or have a confirmed or completed purchase there. 25 per page, most recent buyer first, then clients without purchases (newest first).')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterClientResource::class, Client::class, status: 200, description: 'Paginated clients.', paginate: 25)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'No access to this center\'s clients (center users need reservation access; system users `centers.manage_reservations`), or the token lacks `center-access:{center}`.')]
    #[ApiResponse(['message' => 'The from field must match the format Y-m-d.', 'errors' => ['from' => ['The from field must match the format Y-m-d.']]], 422, 'Invalid filters.')]
    public function index(ListClientsRequest $request, Center $center, CenterClientQuery $query): AnonymousResourceCollection
    {
        $clients = $query->build($center, $request->validated())
            ->paginate(25)
            ->withQueryString();

        return CenterClientResource::collection($clients);
    }

    #[Endpoint('Export the center\'s clients', 'Queues an `.xlsx` export of the clients with their purchase stats, using the list filters. Requires the center permission `clients.export`.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(ExportResource::class, Export::class, status: 202, description: 'The queued export; download it from `GET /api/v1/centers/{center}/exports/{export}/download` once `completed`. The requester gets an `export_ready` or `export_failed` notification.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `clients.export` for this center.')]
    #[ApiResponse(['message' => 'The to field must be a date after or equal to from.', 'errors' => ['to' => ['The to field must be a date after or equal to from.']]], 422, 'Invalid filters.')]
    #[ApiResponse(['message' => 'An export of this kind is already being generated. You will be notified when it is ready.'], 429, 'An export of this kind is already being generated.')]
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

<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Center\BlockClientRequest;
use Modules\Centers\Http\Resources\Center\BlockedClientResource;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Models\Block;
use Modules\Support\Services\BlockService;

#[Group('Blocked clients', 'Clients a center has blocked from booking. Served on `centers/{center}/…` (center-scoped token) and `admin/centers/{center}/…` (system user). Center users need `blocked_clients.manage`; system users need `centers.manage_blocked_clients`.')]
class BlockedClientController extends Controller
{
    public function __construct(private readonly BlockService $blockService) {}

    #[Endpoint('List blocked clients', 'Every client blocked by the center, newest first. Not paginated.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(BlockedClientResource::class, Block::class, status: 200, description: 'Blocked clients.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the permission for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manageBlockedClients', $center);

        $blocks = $center->blocksGiven()
            ->where('blockable_type', (new Client)->getMorphClass())
            ->with('blockable')
            ->latest()
            ->get();

        return response()->json([
            'data' => BlockedClientResource::collection($blocks),
        ]);
    }

    #[Endpoint('Block a client', 'Blocking an already-blocked client updates the reason.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => ['id' => 3, 'client_id' => 77, 'reason' => 'Repeated no-shows.', 'blocked_at' => '2026-05-20T09:30:00.000000Z']], 201, 'The block.')]
    #[ResponseField('data.id', 'integer', 'Block ID.', example: 3)]
    #[ResponseField('data.client_id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('data.reason', 'string', 'Reason.', example: 'Repeated no-shows.')]
    #[ResponseField('data.blocked_at', 'string', 'Block time (ISO-8601).', example: '2026-05-20T09:30:00.000000Z')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the permission for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The reason field is required.', 'errors' => ['reason' => ['The reason field is required.']]], 422, 'Validation failed.')]
    public function store(BlockClientRequest $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manageBlockedClients', $center);

        $block = $this->blockService->block(
            $center,
            Client::query()->findOrFail($request->integer('client_id')),
            $request->string('reason')->toString(),
        );

        return response()->json([
            'data' => [
                'id' => $block->id,
                'client_id' => $block->blockable_id,
                'reason' => $block->reason,
                'blocked_at' => $block->created_at,
            ],
        ], 201);
    }
}

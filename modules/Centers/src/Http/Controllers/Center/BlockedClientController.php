<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Requests\Center\BlockClientRequest;
use Modules\Centers\Http\Resources\Center\BlockedClientResource;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Support\Services\BlockService;

#[Group(name: 'Center / Blocked clients', description: 'Block clients from a center. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class BlockedClientController extends Controller
{
    public function __construct(private readonly BlockService $blockService) {}

    /**
     * List blocked clients.
     *
     * Returns every client blocked by the center, newest first, each with
     * `id`, `client_id`, `client` (full name), `phone_number`, `reason`
     * and `blocked_at`.
     */
    #[Response(status: 200, description: '`data`: `BlockedClientResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $blocks = $center->blocksGiven()
            ->where('blockable_type', (new Client)->getMorphClass())
            ->with('blockable')
            ->latest()
            ->get();

        return response()->json([
            'data' => BlockedClientResource::collection($blocks),
        ]);
    }

    /**
     * Block a client.
     *
     * Blocks the client on behalf of the center with a mandatory reason.
     * Blocking an already-blocked client updates the reason.
     */
    #[Response(status: 201, description: 'The block `id`, `client_id`, `reason` and `blocked_at`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    #[Response(status: 422, description: 'Validation failed (`client_id` or `reason`).')]
    public function store(BlockClientRequest $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

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

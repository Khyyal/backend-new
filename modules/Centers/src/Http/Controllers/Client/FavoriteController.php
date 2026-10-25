<?php

namespace Modules\Centers\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Centers\Http\Requests\Client\FavoriteCenterRequest;
use Modules\Centers\Http\Resources\Client\CenterResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\FavoriteService;
use Modules\Clients\Models\Client;

#[Group(name: 'Client / Favorites', description: 'Authenticated client favorite centers management.')]
class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
    ) {}

    /**
     * List the authenticated client's favorite centers.
     *
     * Paginated (15 per page, `?page=`), most recently favorited first.
     *
     * **Authentication:** Requires a valid Bearer token issued by the
     * `/clients/auth/verify` endpoint for the `client` guard.
     */
    #[Response(status: 200, description: 'Paginated `CenterResource` collection of the client\'s favorite centers.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `client` guard.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Client $client */
        $client = $request->user('client');

        return CenterResource::collection($this->favoriteService->list($client));
    }

    /**
     * Favorite a center.
     *
     * Adds the given center to the authenticated client's favorites.
     * Idempotent — favoriting an already-favorited center simply confirms it.
     *
     * **Request body:**
     * - `center_id` — required, integer, must exist in the `centers` table.
     *
     * @param  FavoriteCenterRequest  $request  Validated via {@see FavoriteCenterRequest}.
     */
    #[Response(status: 201, description: 'Center added to favorites.')]
    #[Response(status: 200, description: 'Center was already in the client\'s favorites.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `client` guard.')]
    #[Response(status: 422, description: 'Validation failure on `center_id`.')]
    public function store(FavoriteCenterRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');

        /** @var Center $center */
        $center = Center::findOrFail($request->validated()['center_id']);

        $alreadyFavorited = $this->favoriteService->isFavorited($client, $center);

        $this->favoriteService->add($client, $center);

        return response()->json([
            'message' => $alreadyFavorited
                ? __('messages.favorite_already_exists')
                : __('messages.favorite_added'),
            'favorite' => [
                'center_id' => $center->getKey(),
                'client_id' => $client->getKey(),
            ],
        ], $alreadyFavorited ? 200 : 201);
    }

    /**
     * Unfavorite a center.
     *
     * Removes the given center from the authenticated client's favorites.
     *
     * **Authentication:** Requires a valid Bearer token for the `client` guard.
     */
    #[Response(status: 200, description: 'Center removed from favorites.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `client` guard.')]
    #[Response(status: 404, description: 'Center was not in the client\'s favorites.')]
    public function destroy(Request $request, Center $center): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');

        if (! $this->favoriteService->remove($client, $center)) {
            return response()->json([
                'message' => __('messages.favorite_not_found'),
            ], 404);
        }

        return response()->json([
            'message' => __('messages.favorite_removed'),
        ]);
    }
}

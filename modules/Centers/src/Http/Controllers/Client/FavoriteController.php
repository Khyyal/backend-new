<?php

namespace Modules\Centers\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Client\FavoriteCenterRequest;
use Modules\Centers\Http\Resources\Client\CenterResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\FavoriteService;
use Modules\Clients\Models\Client;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Favorites', 'The client\'s favorite centers.')]
class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
    ) {}

    #[Endpoint('List favorite centers', 'Most recently favorited first, 15 per page.')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'Paginated favorite centers.', paginate: 15, with: ['city'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Client $client */
        $client = $request->user('client');

        return CenterResource::collection($this->favoriteService->list($client));
    }

    #[Endpoint('Favorite a center', 'Idempotent: 201 when added, 200 when it was already a favorite.')]
    #[ApiResponse(['message' => 'messages.favorite_added', 'favorite' => ['center_id' => 8, 'client_id' => 77]], 201, 'Added.')]
    #[ApiResponse(['message' => 'messages.favorite_already_exists', 'favorite' => ['center_id' => 8, 'client_id' => 77]], 200, 'Already a favorite.')]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.favorite_added')]
    #[ResponseField('favorite.center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('favorite.client_id', 'integer', 'Client ID.', example: 77)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The selected center id is invalid.', 'errors' => ['center_id' => ['The selected center id is invalid.']]], 422, 'Unknown center.')]
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

    #[Endpoint('Unfavorite a center')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 8)]
    #[ApiResponse(['message' => 'messages.favorite_removed'], 200, 'Removed.')]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.favorite_removed')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'messages.favorite_not_found'], 404, 'The center was not a favorite (or does not exist).')]
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

<?php

namespace Modules\Clients\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Centers\Models\Center;
use Modules\Clients\Http\Requests\Client\RateCenterRequest;
use Modules\Clients\Models\Client;
use Modules\Support\Services\RatingService;

#[Group(name: 'Client / Rating', description: 'Authenticated client rating management.')]
class RatingController extends Controller
{
    public function __construct(
        private readonly RatingService $ratingService,
    ) {}

    /**
     * Rate a center.
     *
     * Creates a new rating for the specified center from the authenticated client.
     * The client must provide a star rating between 1 and 5, and may optionally
     * include a text comment.
     *
     * **Authentication:** Requires a valid Bearer token issued by the
     * `/clients/auth/verify` endpoint for the `client` guard.
     *
     * **Request body:**
     * - `center_id` — required, integer, must exist in the `centers` table.
     * - `stars`     — required, integer, between 1 and 5 inclusive.
     * - `comment`   — optional, string, max 1000 characters.
     *
     * @param RateCenterRequest $request Validated via {@see RateCenterRequest}.
     * @return JsonResponse 201 with the created `rating` and a confirmation `message`.
     */
    #[Response(
        status: 201,
        description: 'Rating created successfully. Returns the `rating` object and a localized `message`.'
    )]
    #[Response(
        status: 401,
        description: 'Missing or invalid Bearer token for the `client` guard.'
    )]
    #[Response(
        status: 422,
        description: 'Validation failure on `center_id`, `stars`, or `comment`.'
    )]
    public function rate(RateCenterRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');

        /** @var Center $center */
        $center = Center::findOrFail($request->validated()['center_id']);

        $rating = $this->ratingService->create(
            rater: $client,
            rateable: $center,
            stars: (int) $request->validated()['stars'],
            comment: $request->validated()['comment'] ?? null,
        );

        return response()->json([
            'message' => __('messages.rating_created'),
            'rating' => [
                'id' => $rating->getKey(),
                'stars' => $rating->stars,
                'comment' => $rating->comment,
                'center_id' => $center->getKey(),
                'rater_id' => $client->getKey(),
            ],
        ], 201);
    }
}

<?php

namespace Modules\Centers\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Http\Requests\Client\RateCenterRequest;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Services\RatingService;

#[Group('Client / Ratings', 'Client ratings of centers.')]
class RatingController extends Controller
{
    public function __construct(
        private readonly RatingService $ratingService,
    ) {}

    #[Endpoint('Rate a center', 'Creates a rating from the authenticated client.')]
    #[ApiResponse(['message' => 'messages.rating_created', 'rating' => ['id' => 21, 'stars' => 5, 'comment' => 'Great trainers!', 'center_id' => 8, 'rater_id' => 77]], 201, 'Rating created.')]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.rating_created')]
    #[ResponseField('rating.id', 'integer', 'Rating ID.', example: 21)]
    #[ResponseField('rating.stars', 'integer', 'Stars, 1-5.', example: 5)]
    #[ResponseField('rating.comment', 'string', 'Comment.', nullable: true, example: 'Great trainers!')]
    #[ResponseField('rating.center_id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('rating.rater_id', 'integer', 'Client ID.', example: 77)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The stars field must not be greater than 5.', 'errors' => ['stars' => ['The stars field must not be greater than 5.']]], 422, 'Validation failed.')]
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

        Center::flushSearchCache();

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

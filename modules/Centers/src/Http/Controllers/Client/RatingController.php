<?php

namespace Modules\Centers\Http\Controllers\Client;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Client\RateCenterRequest;
use Modules\Centers\Http\Requests\Client\UpdateRatingRequest;
use Modules\Centers\Http\Resources\Client\RatingResource;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Models\Rating;
use Modules\Support\Services\RatingService;

#[Group('Client / Ratings', 'Client ratings of centers.')]
class RatingController extends Controller
{
    /**
     * Accepted `sort` values of the client's ratings list.
     *
     * @var list<string>
     */
    public const array SORTS = ['newest', 'highest'];

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

    #[Endpoint('List my ratings', 'The ratings you gave to centers, 15 per page.')]
    #[QueryParam('sort', 'string', '`newest` (default) for the latest first, `highest` for the most stars first.', required: false, enum: self::SORTS, example: 'highest')]
    #[QueryParam('page', 'integer', 'Page number (1-based).', required: false, example: 1)]
    #[ResponseFromApiResource(RatingResource::class, Rating::class, status: 200, description: 'Paginated ratings.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The selected sort is invalid.', 'errors' => ['sort' => ['The selected sort is invalid.']]], 422, 'Validation failed.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ]);

        /** @var Client $client */
        $client = $request->user('client');

        $ratings = $this->centerRatingsOf($client)
            ->with('rateable')
            ->when(($filters['sort'] ?? null) === 'highest', fn ($query) => $query->orderByDesc('stars'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return RatingResource::collection($ratings);
    }

    #[Endpoint('Edit my rating', 'Changes the stars and/or the comment of one of your ratings. Fields you omit keep their value.')]
    #[UrlParam('rating', 'integer', 'Rating ID.', example: 21)]
    #[ApiResponse(['message' => 'messages.rating_updated', 'rating' => ['id' => 21, 'stars' => 4, 'comment' => 'Good overall.', 'center' => ['id' => 8, 'name' => 'Al Fursan Stables'], 'rated_at' => '2026-05-20T09:30:00+00:00', 'updated_at' => '2026-05-21T10:00:00+00:00']], 200, 'Rating updated.')]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.rating_updated')]
    #[ResponseField('rating', 'object', 'The updated rating (`RatingResource`).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such rating of yours.')]
    #[ApiResponse(['message' => 'The stars field must not be greater than 5.', 'errors' => ['stars' => ['The stars field must not be greater than 5.']]], 422, 'Validation failed.')]
    public function update(UpdateRatingRequest $request, int $rating): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');

        /** @var Rating $existing */
        $existing = $this->centerRatingsOf($client)->findOrFail($rating);
        $data = $request->validated();

        $updated = $this->ratingService->update(
            $existing,
            stars: (int) ($data['stars'] ?? $existing->stars),
            comment: array_key_exists('comment', $data) ? $data['comment'] : $existing->comment,
        );

        Center::flushSearchCache();

        return response()->json([
            'message' => __('messages.rating_updated'),
            'rating' => RatingResource::make($updated->load('rateable')),
        ]);
    }

    /**
     * The ratings a client gave to centers.
     *
     * @return MorphMany<Rating, Client>
     */
    private function centerRatingsOf(Client $client): MorphMany
    {
        return $client->ratingsGiven()->where('rateable_type', (new Center)->getMorphClass());
    }
}

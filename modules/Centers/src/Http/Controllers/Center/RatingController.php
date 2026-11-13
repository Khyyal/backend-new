<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Resources\Center\RatingResource;
use Modules\Centers\Models\Center;
use Modules\Support\Documentation\ApiExamples;

#[Group('Ratings', 'Ratings clients gave a center. Served on `centers/{center}/…` (center-scoped token) and `admin/centers/{center}/…` (system user). Center users need `ratings.view`; system users need `centers.view_ratings`.')]
class RatingController extends Controller
{
    /**
     * Accepted `sort` values of the center's ratings list.
     *
     * @var list<string>
     */
    public const array SORTS = ['newest', 'highest'];

    #[Endpoint('List the center\'s ratings', 'A summary plus every rating, newest first unless `sort` says otherwise. Not paginated.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[QueryParam('sort', 'string', '`newest` (default) for the latest first, `highest` for the most stars first.', required: false, enum: self::SORTS, example: 'highest')]
    #[ApiResponse(['summary' => ['average' => 4.6, 'count' => 18], 'data' => [['id' => 21, 'client' => 'Noura Al-Qahtani', 'stars' => 5, 'comment' => 'Great trainers!', 'rated_at' => '2026-05-20T09:30:00.000000Z']]], 200, 'Summary and ratings.')]
    #[ResponseField('summary.average', 'number', 'Average stars; null without ratings.', nullable: true, example: 4.6)]
    #[ResponseField('summary.count', 'integer', 'Number of ratings.', example: 18)]
    #[ResponseField('data', 'object[]', 'Ratings (`RatingResource`): id, client, stars, comment, rated_at.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `ratings.view` for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The selected sort is invalid.', 'errors' => ['sort' => ['The selected sort is invalid.']]], 422, 'Validation failed.')]
    public function index(Request $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewRatings', $center);

        $filters = $request->validate([
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ]);

        $ratings = $center->ratings()
            ->with('rater')
            ->when(($filters['sort'] ?? null) === 'highest', fn ($query) => $query->orderByDesc('stars'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'summary' => [
                'average' => $center->averageRating(),
                'count' => $center->ratingsCount(),
            ],
            'data' => RatingResource::collection($ratings),
        ]);
    }
}

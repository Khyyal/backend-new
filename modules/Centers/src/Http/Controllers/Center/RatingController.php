<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Http\Resources\Center\RatingResource;
use Modules\Centers\Models\Center;

#[Group(name: 'Center / Ratings', description: "View a center's own ratings. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).")]
class RatingController extends Controller
{
    /**
     * List the center's ratings.
     *
     * Returns a `summary` (`average` stars, `count`) and `data`: every
     * rating, newest first, each with `id`, `client` (the rater's full
     * name), `stars`, `comment` and `rated_at`.
     */
    #[Response(status: 200, description: '`summary` ({average, count}) and `data` (`RatingResource` collection: id, client, stars, comment, rated_at).')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user is not assigned to it.')]
    public function index(Request $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user('center_user'))->authorize('manageServices', $center);

        $ratings = $center->ratings()->with('rater')->latest()->get();

        return response()->json([
            'summary' => [
                'average' => $center->averageRating(),
                'count' => $center->ratingsCount(),
            ],
            'data' => RatingResource::collection($ratings),
        ]);
    }
}

<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterProfileCompletion;

#[Group(name: 'Center / Profile', description: 'Whether the center profile is complete and what is left to do. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class ProfileCompletionController extends Controller
{
    public function __construct(private readonly CenterProfileCompletion $completion) {}

    /**
     * Show the center profile completion.
     *
     * Every step with its `done` flag, the `fields` to send and the `action`
     * (HTTP method and path) that completes it. Steps, in order: `city`,
     * `description`, `address`, `location` (lat and lng), `logo`, `cover`,
     * `images` (at least one), `tags` (at least one) and `service` (at least
     * one active service; create it through the service endpoints listed by
     * `GET centers/{center}/service-types`).
     *
     * `missing` lists the keys of the steps still to do; `is_complete` is true
     * when none is left. Any user assigned to the center can read it.
     */
    #[Response(status: 200, description: '`{data: {is_complete, completed_steps, total_steps, percentage, missing, steps: [{key, done, fields, action: {method, path}}]}}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 403, description: 'The user is not assigned to this center, or the token is not scoped to it.')]
    public function __invoke(Request $request, Center $center): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('center_user');

        abort_unless($user->centers()->where('center_id', $center->id)->exists(), 403);

        return response()->json(['data' => $this->completion->for($center)]);
    }
}

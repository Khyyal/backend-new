<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterProfileCompletion;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Profile', 'How complete the center profile is and what is left to do.')]
class ProfileCompletionController extends Controller
{
    public function __construct(private readonly CenterProfileCompletion $completion) {}

    #[Endpoint('Show the profile completion', 'Steps in order: `city`, `description`, `address`, `location` (lat and lng), `logo`, `cover`, `images` (at least one), `tags` (at least one), `service` (at least one active service) and `email` (contact email set and verified: `POST …/email/send`, then `POST …/email/verify`). Any user assigned to the center can read it.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => ['is_complete' => false, 'completed_steps' => 8, 'total_steps' => 10, 'percentage' => 80, 'missing' => ['images', 'service'], 'steps' => [['key' => 'city', 'done' => true, 'fields' => ['city_id'], 'action' => ['method' => 'PATCH', 'path' => '/api/v1/centers/8']], ['key' => 'images', 'done' => false, 'fields' => ['images'], 'action' => ['method' => 'PATCH', 'path' => '/api/v1/centers/8']]]]], 200, 'Completion state.')]
    #[ResponseField('data.is_complete', 'boolean', 'True when no step is left.', example: false)]
    #[ResponseField('data.completed_steps', 'integer', 'Steps done.', example: 8)]
    #[ResponseField('data.total_steps', 'integer', 'All steps.', example: 10)]
    #[ResponseField('data.percentage', 'integer', 'Completion percent (0-100).', example: 80)]
    #[ResponseField('data.missing', 'string[]', 'Keys of the steps still to do.', example: ['images', 'service'])]
    #[ResponseField('data.steps', 'object[]', 'Every step.')]
    #[ResponseField('data.steps[].key', 'string', 'Step key.', enum: ['city', 'description', 'address', 'location', 'logo', 'cover', 'images', 'tags', 'service', 'email'], example: 'city')]
    #[ResponseField('data.steps[].done', 'boolean', 'Whether the step is complete.', example: true)]
    #[ResponseField('data.steps[].fields', 'string[]', 'Request fields that complete it.', example: ['city_id'])]
    #[ResponseField('data.steps[].action', 'object', 'Endpoint that completes it: `method` and `path`.', example: ['method' => 'PATCH', 'path' => '/api/v1/centers/8'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not assigned to this center, or the token lacks `center-access:{center}`.')]
    public function __invoke(Request $request, Center $center): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('center_user');

        abort_unless($user->centers()->where('center_id', $center->id)->exists(), 403);

        return response()->json(['data' => $this->completion->for($center)]);
    }
}

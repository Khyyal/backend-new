<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Center\UpdateProfileRequest;
use Modules\Centers\Http\Resources\Center\UserResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterAccountService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Account', 'The primary user\'s own profile and deleting the center account.')]
class AccountController extends Controller
{
    public function __construct(private readonly CenterAccountService $accountService) {}

    #[Endpoint('Update my profile', 'Primary user only. Partial update of the name and access password. Changing an existing password requires `current_password`. The phone cannot be changed here.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(UserResource::class, User::class, status: 200, description: 'The updated user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the primary user of this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The password field must be at least 8 characters.', 'errors' => ['password' => ['The password field must be at least 8 characters.']]], 422, 'Validation failed, or `current_password` is wrong.')]
    public function updateProfile(UpdateProfileRequest $request, Center $center): UserResource
    {
        /** @var User $user */
        $user = $request->user('center_user');

        return UserResource::make($this->accountService->updateProfile($user, $request->validated()));
    }

    #[Endpoint('Delete the center account', 'Primary user only. Deletes the center and removes all its users: their access is revoked, and accounts left without a center are deleted. Refused while the center has upcoming pending or confirmed reservations, or a pending payout or unsettled balance.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the primary user of this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The center has upcoming pending or confirmed reservations. Cancel them before deleting the account.', 'reason' => 'has_open_reservations', 'errors' => ['center' => ['The center has upcoming pending or confirmed reservations. Cancel them before deleting the account.']]], 422, 'Open obligations remain. `reason` is `has_open_reservations` or `has_unsettled_balance`.')]
    public function destroy(Request $request, Center $center): HttpResponse
    {
        Gate::forUser($request->user('center_user'))->authorize('deleteOwn', $center);

        $this->accountService->delete($center);

        return response()->noContent();
    }
}

<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Http\Requests\Center\StoreCenterUserRequest;
use Modules\Centers\Http\Requests\Center\UpdateCenterUserRequest;
use Modules\Centers\Http\Resources\Center\CenterMemberResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterUserManagementService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Users', 'The users of a center and their permissions. The primary user, or a user with `users.manage`, manages them.')]
class CenterUserController extends Controller
{
    public function __construct(private readonly CenterUserManagementService $userService) {}

    #[Endpoint('List center users', 'Primary user first, each with status, linked trainer and permissions. Not paginated.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterMemberResource::class, User::class, status: 200, description: 'Center users.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `users.manage` for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        return CenterMemberResource::collection($this->userService->list($center));
    }

    #[Endpoint('List grantable permissions', 'The permission names that can be granted to center users.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['data' => ['services.training.manage', 'purchases.training.view', 'users.manage']], 200, 'Permission names.')]
    #[ResponseField('data', 'string[]', 'Every center permission (`CenterUserPermission`).', example: ['services.training.manage', 'purchases.training.view'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `users.manage` for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function permissions(Request $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        return response()->json(['data' => CenterUserPermission::all()]);
    }

    #[Endpoint('Add a center user', 'Assigns a user to the center. An existing account with the phone is assigned as-is (its name is kept); otherwise one is created. The user signs in by OTP.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterMemberResource::class, User::class, status: 201, description: 'The assigned user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `users.manage` for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The user is already assigned to this center.', 'errors' => ['phone' => ['The user is already assigned to this center.']]], 422, 'Validation failed, the trainer is invalid or already linked, the phone already belongs to a user of this center, or a permission you do not hold.')]
    public function store(StoreCenterUserRequest $request, Center $center): JsonResponse
    {
        $member = $this->userService->create($center, $request->validated(), $request->user('center_user'));

        return CenterMemberResource::make($member)->response()->setStatusCode(201);
    }

    #[Endpoint('Show a center user')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('user', 'integer', 'Center user ID (must be assigned to the center).', example: 15)]
    #[ResponseFromApiResource(CenterMemberResource::class, User::class, status: 200, description: 'The user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `users.manage` for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such user in this center.')]
    public function show(Request $request, Center $center, int $user): CenterMemberResource
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        return CenterMemberResource::make($this->userService->findMember($center, $user));
    }

    #[Endpoint('Update a center user', 'Partial update. `permissions` is the full list. The primary user and yourself cannot be updated here; a non-primary manager only grants permissions they hold.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('user', 'integer', 'Center user ID (must be assigned to the center).', example: 15)]
    #[ResponseFromApiResource(CenterMemberResource::class, User::class, status: 200, description: 'The updated user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `users.manage` for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such user in this center.')]
    #[ApiResponse(['message' => 'The center\'s primary user cannot be modified or removed.', 'errors' => ['user' => ['The center\'s primary user cannot be modified or removed.']]], 422, 'Validation failed, or the target is the primary user or yourself.')]
    public function update(UpdateCenterUserRequest $request, Center $center, int $user): CenterMemberResource
    {
        $member = $this->userService->findMember($center, $user);

        return CenterMemberResource::make($this->userService->update($center, $member, $request->validated(), $request->user('center_user')));
    }

    #[Endpoint('Remove a center user', 'Unassigns the user and revokes their access to the center. An account left without centers is deleted. The primary user cannot be removed.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('user', 'integer', 'Center user ID (must be assigned to the center).', example: 15)]
    #[ApiResponse(status: 204, description: 'Removed. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `users.manage` for this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such user in this center.')]
    #[ApiResponse(['message' => 'The center\'s primary user cannot be modified or removed.', 'errors' => ['user' => ['The center\'s primary user cannot be modified or removed.']]], 422, 'The target is the primary user.')]
    public function destroy(Request $request, Center $center, int $user): HttpResponse
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        $this->userService->remove($center, $this->userService->findMember($center, $user), $request->user());

        return response()->noContent();
    }
}

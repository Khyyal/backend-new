<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Http\Requests\Center\StoreCenterUserRequest;
use Modules\Centers\Http\Requests\Center\UpdateCenterUserRequest;
use Modules\Centers\Http\Resources\Center\CenterMemberResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterUserManagementService;

#[Group(name: 'Center / Users', description: 'The center\'s primary user, or a user granted `users.manage`, manages the other users of the center and their permissions. Requires a center-scoped Bearer token (see `POST /centers/{center}/access`).')]
class CenterUserController extends Controller
{
    public function __construct(private readonly CenterUserManagementService $userService) {}

    /**
     * List center users.
     *
     * Returns every user assigned to the center, primary user first, each with
     * `id`, `name`, `phone`, `is_primary`, `status`, `joined_at`, the linked
     * `trainer` (`null` for the primary user) and the `permissions` they hold
     * on the center.
     */
    #[Response(status: 200, description: '`data`: `CenterMemberResource` collection.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token for the `center_user` guard.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user lacks `users.manage`.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        return CenterMemberResource::collection($this->userService->list($center));
    }

    /**
     * List grantable permissions.
     *
     * Returns the permission names that can be granted to center users.
     */
    #[Response(status: 200, description: '`data`: list of permission names.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user lacks `users.manage`.')]
    public function permissions(Request $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        return response()->json(['data' => CenterUserPermission::all()]);
    }

    /**
     * Add a center user.
     *
     * Assigns a user to the center with the given `permissions`. When an
     * account with the `phone` already exists it is assigned as-is (its name is
     * kept); otherwise a new account is created. The user signs in by OTP.
     *
     * `trainer_id` is required: a trainer of the center not already linked to
     * another user of the center.
     */
    #[Response(status: 201, description: 'The assigned user as a `CenterMemberResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user lacks `users.manage`.')]
    #[Response(status: 422, description: 'Validation failed, the trainer is invalid or already linked, or the phone already belongs to a user of this center.')]
    public function store(StoreCenterUserRequest $request, Center $center): JsonResponse
    {
        $member = $this->userService->create($center, $request->validated(), $request->user('center_user'));

        return CenterMemberResource::make($member)->response()->setStatusCode(201);
    }

    /**
     * Show a center user.
     */
    #[Response(status: 200, description: 'The user as a `CenterMemberResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user lacks `users.manage`.')]
    #[Response(status: 404, description: 'No user with this id is assigned to the center.')]
    public function show(Request $request, Center $center, int $user): CenterMemberResource
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        return CenterMemberResource::make($this->userService->findMember($center, $user));
    }

    /**
     * Update a center user.
     *
     * Partial update of `name`, `status`, `trainer_id` and `permissions` (the
     * full list of permissions the user holds on the center). The primary user
     * and the acting user cannot be updated here; a non-primary manager can only
     * grant permissions they hold.
     */
    #[Response(status: 200, description: 'The updated user as a `CenterMemberResource`.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user lacks `users.manage`.')]
    #[Response(status: 404, description: 'No user with this id is assigned to the center.')]
    #[Response(status: 422, description: 'Validation failed, or the target is the primary user.')]
    public function update(UpdateCenterUserRequest $request, Center $center, int $user): CenterMemberResource
    {
        $member = $this->userService->findMember($center, $user);

        return CenterMemberResource::make($this->userService->update($center, $member, $request->validated(), $request->user('center_user')));
    }

    /**
     * Remove a center user.
     *
     * Unassigns the user from the center and revokes their access to it. An
     * account that no longer belongs to any center is deleted. The primary
     * user cannot be removed.
     */
    #[Response(status: 204, description: 'The user was removed from the center.')]
    #[Response(status: 403, description: 'The token has no access to this center, or the user lacks `users.manage`.')]
    #[Response(status: 404, description: 'No user with this id is assigned to the center.')]
    #[Response(status: 422, description: 'The target is the primary user.')]
    public function destroy(Request $request, Center $center, int $user): HttpResponse
    {
        Gate::forUser($request->user())->authorize('manageUsers', $center);

        $this->userService->remove($center, $this->userService->findMember($center, $user), $request->user());

        return response()->noContent();
    }
}

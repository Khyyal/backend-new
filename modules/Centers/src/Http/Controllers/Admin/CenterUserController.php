<?php

namespace Modules\Centers\Http\Controllers\Admin;

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
use Modules\Centers\Http\Requests\Admin\StoreCenterUserRequest;
use Modules\Centers\Http\Requests\Admin\UpdateCenterUserRequest;
use Modules\Centers\Http\Resources\Admin\CenterUserResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterUserManagementService;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Resources\Admin\IssuedOtpResource;
use Modules\Support\Services\OtpVerificationService;

#[Group('Admin / Centers', 'Centers as managed by system users. Requires the matching `centers.*` permission.')]
class CenterUserController extends Controller
{
    public function __construct(private readonly CenterUserManagementService $userService) {}

    #[Endpoint('List center users', 'The center\'s staff accounts, primary user first, each with status, linked trainer and permissions. Not paginated.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[ResponseFromApiResource(CenterUserResource::class, User::class, status: 200, description: 'The center\'s users.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('view', $center);

        return CenterUserResource::collection($this->userService->list($center));
    }

    #[Endpoint('List grantable permissions', 'The permission names that can be granted to center users.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[ApiResponse(['data' => ['services.training.manage', 'purchases.training.view', 'users.manage']], 200, 'Permission names.')]
    #[ResponseField('data', 'string[]', 'Every center permission (`CenterUserPermission`).', example: ['services.training.manage', 'purchases.training.view'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID.')]
    public function permissions(Request $request, Center $center): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $center);

        return response()->json(['data' => CenterUserPermission::all()]);
    }

    #[Endpoint('Add a center user', 'Assigns a user to the center with any permissions. An existing account with the phone is assigned as-is (its name is kept); otherwise one is created. The user signs in by OTP.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[ResponseFromApiResource(CenterUserResource::class, User::class, status: 201, description: 'The assigned user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID.')]
    #[ApiResponse(['message' => 'The user is already assigned to this center.', 'errors' => ['phone' => ['The user is already assigned to this center.']]], 422, 'Validation failed, the trainer is invalid or already linked, or the phone already belongs to a user of this center.')]
    public function store(StoreCenterUserRequest $request, Center $center): JsonResponse
    {
        $member = $this->userService->createByAdmin($center, $request->validated());

        return CenterUserResource::make($member)->response()->setStatusCode(201);
    }

    #[Endpoint('Show a center user')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[UrlParam('user', 'integer', 'Center user ID (must be assigned to the center).', example: 12)]
    #[ResponseFromApiResource(CenterUserResource::class, User::class, status: 200, description: 'The user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID, or no such user in it.')]
    public function show(Request $request, Center $center, int $user): CenterUserResource
    {
        Gate::forUser($request->user())->authorize('view', $center);

        return CenterUserResource::make($this->userService->findMember($center, $user));
    }

    #[Endpoint('Update a center user', 'Partial update; `permissions` is the full list. For the primary user only `name` and `phone` are accepted. A changed phone must be verified again and signs the account out everywhere.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[UrlParam('user', 'integer', 'Center user ID (must be assigned to the center).', example: 12)]
    #[ResponseFromApiResource(CenterUserResource::class, User::class, status: 200, description: 'The updated user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID, or no such user in it.')]
    #[ApiResponse(['message' => 'Only the name and phone of the center\'s primary user can be changed.', 'errors' => ['user' => ['Only the name and phone of the center\'s primary user can be changed.']]], 422, 'Validation failed, the phone is taken, or a field other than name or phone was sent for the primary user.')]
    public function update(UpdateCenterUserRequest $request, Center $center, int $user): CenterUserResource
    {
        $member = $this->userService->findMember($center, $user);

        return CenterUserResource::make($this->userService->updateByAdmin($center, $member, $request->validated()));
    }

    #[Endpoint('Remove a center user', 'Unassigns the user and revokes their access to the center. An account left without centers is deleted. The primary user cannot be removed.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[UrlParam('user', 'integer', 'Center user ID (must be assigned to the center).', example: 12)]
    #[ApiResponse(status: 204, description: 'Removed. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.update`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID, or no such user in it.')]
    #[ApiResponse(['message' => 'The center\'s primary user cannot be modified or removed.', 'errors' => ['user' => ['The center\'s primary user cannot be modified or removed.']]], 422, 'The target is the primary user.')]
    public function destroy(Request $request, Center $center, int $user): HttpResponse
    {
        Gate::forUser($request->user())->authorize('manageUsersAsAdmin', $center);

        $this->userService->removeByAdmin($center, $this->userService->findMember($center, $user));

        return response()->noContent();
    }

    #[Endpoint('Issue a login OTP for a center user', 'Generates a fresh 4-digit login code for the user\'s phone and returns it once so support can read it to them. No SMS is sent; older unused codes for the phone stop working. Valid for 5 minutes. Throttled to 10 requests per minute.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[UrlParam('user', 'integer', 'Center user ID. Must belong to the center.', example: 12)]
    #[ApiResponse(['data' => ['phone' => '0551234567', 'code' => '0427', 'expires_at' => '2026-11-10T09:35:00+00:00', 'expires_in_seconds' => 300]], 201, 'The issued code.')]
    #[ResponseField('data.phone', 'string', 'Phone the code is valid for.', example: '0551234567')]
    #[ResponseField('data.code', 'string', 'The 4-digit code. Shown only in this response.', example: '0427')]
    #[ResponseField('data.expires_at', 'string', 'When the code expires (ISO-8601).', example: '2026-11-10T09:35:00+00:00')]
    #[ResponseField('data.expires_in_seconds', 'integer', 'Seconds until the code expires.', example: 300)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `otps.create`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID, or the user does not belong to it.')]
    #[ApiResponse(['message' => 'Too Many Attempts.'], 429, 'More than 10 requests in a minute.')]
    public function issueOtp(Request $request, Center $center, int $user, OtpVerificationService $otps): JsonResponse
    {
        Gate::forUser($request->user())->authorize('createOtp', $center);

        /** @var User $centerUser */
        $centerUser = $center->users()->whereKey($user)->firstOrFail();

        $issued = $otps->issue($centerUser->phone);

        return IssuedOtpResource::make(['phone' => $centerUser->phone, ...$issued])
            ->response()
            ->setStatusCode(201);
    }
}

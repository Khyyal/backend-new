<?php

namespace Modules\Support\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Requests\Admin\ListSystemUsersRequest;
use Modules\Support\Http\Requests\Admin\StoreSystemUserRequest;
use Modules\Support\Http\Requests\Admin\UpdateSystemUserRequest;
use Modules\Support\Http\Resources\Admin\SystemUserResource;
use Modules\Support\Models\SystemUser;
use Modules\Support\Services\SystemUserService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / System users', 'Admin accounts. Requires the matching `system_users.*` permission. Only super admins change other super admins or grant `super_admin`, and the last active super admin is always kept.')]
class SystemUserController extends Controller
{
    public function __construct(private readonly SystemUserService $systemUsers) {}

    #[Endpoint('List system users', 'Newest first, paginated, with roles and permissions. Filters are query parameters.')]
    #[ResponseFromApiResource(SystemUserResource::class, SystemUser::class, status: 200, description: 'Paginated system users.', paginate: 15)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_users.view_any`.')]
    public function index(ListSystemUsersRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $users = SystemUser::query()
            ->with('roles.permissions')
            ->when($filters['trashed'] ?? null, fn ($query, string $trashed) => $trashed === 'only' ? $query->onlyTrashed() : $query->withTrashed())
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
            ))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->whereHas('roles', fn ($query) => $query->where('name', $role)))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return SystemUserResource::collection($users);
    }

    #[Endpoint('Create a system user')]
    #[ResponseFromApiResource(SystemUserResource::class, SystemUser::class, status: 201, description: 'The created user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_users.create` (or `system_users.assign_roles` when sending `roles`), or granting `super_admin` without being one.')]
    #[ApiResponse(['message' => 'The email has already been taken.', 'errors' => ['email' => ['The email has already been taken.']]], 422, 'Validation failed.')]
    public function store(StoreSystemUserRequest $request): JsonResponse
    {
        $user = $this->systemUsers->create($request->user(), $request->validated());

        return SystemUserResource::make($user)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a system user', 'Deleted users are returned too (see `deleted_at`).')]
    #[UrlParam('systemUser', 'integer', 'System user ID.', example: 2)]
    #[ResponseFromApiResource(SystemUserResource::class, SystemUser::class, status: 200, description: 'The user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_users.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No user with that ID.')]
    public function show(Request $request, SystemUser $systemUser): SystemUserResource
    {
        Gate::forUser($request->user())->authorize('view', $systemUser);

        return SystemUserResource::make($systemUser);
    }

    #[Endpoint('Update a system user', 'Partial update. A new `password` or `status: inactive` revokes the user\'s tokens. `roles` replaces the roles and needs `system_users.assign_roles`. Nobody changes their own `status` or `roles`.')]
    #[UrlParam('systemUser', 'integer', 'System user ID.', example: 2)]
    #[ResponseFromApiResource(SystemUserResource::class, SystemUser::class, status: 200, description: 'The updated user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing the permission, or the change targets yourself or a super admin.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No user with that ID.')]
    #[ApiResponse(['message' => 'At least one active super admin is required.', 'errors' => ['status' => ['At least one active super admin is required.']]], 422, 'Validation failed, or the change would leave no active super admin.')]
    public function update(UpdateSystemUserRequest $request, SystemUser $systemUser): SystemUserResource
    {
        return SystemUserResource::make($this->systemUsers->update($request->user(), $systemUser, $request->validated()));
    }

    #[Endpoint('Delete a system user', 'Soft delete; revokes every token. Nobody deletes themselves.')]
    #[UrlParam('systemUser', 'integer', 'System user ID.', example: 2)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_users.delete`, or the target is yourself.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No user with that ID.')]
    #[ApiResponse(['message' => 'At least one active super admin is required.', 'errors' => ['user' => ['At least one active super admin is required.']]], 422, 'The user is the last active super admin.')]
    public function destroy(Request $request, SystemUser $systemUser): Response
    {
        Gate::forUser($request->user())->authorize('delete', $systemUser);

        $this->systemUsers->delete($systemUser);

        return response()->noContent();
    }

    #[Endpoint('Restore a deleted system user')]
    #[UrlParam('systemUser', 'integer', 'System user ID.', example: 2)]
    #[ResponseFromApiResource(SystemUserResource::class, SystemUser::class, status: 200, description: 'The restored user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_users.restore`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No user with that ID.')]
    public function restore(Request $request, SystemUser $systemUser): SystemUserResource
    {
        Gate::forUser($request->user())->authorize('restore', $systemUser);

        return SystemUserResource::make($this->systemUsers->restore($systemUser));
    }
}

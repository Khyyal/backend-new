<?php

namespace Modules\Support\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Support\Http\Requests\Admin\ListSystemUsersRequest;
use Modules\Support\Http\Requests\Admin\StoreSystemUserRequest;
use Modules\Support\Http\Requests\Admin\UpdateSystemUserRequest;
use Modules\Support\Http\Resources\Admin\SystemUserResource;
use Modules\Support\Models\SystemUser;
use Modules\Support\Services\SystemUserService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / System users', description: 'Management of admin accounts. Requires a system user Bearer token with the matching `system_users.*` permission. Only super admins may change other super admins or grant the super admin role, and the last active super admin is always kept.')]
class SystemUserController extends Controller
{
    public function __construct(private readonly SystemUserService $systemUsers) {}

    /**
     * List system users.
     *
     * Paginated (15 per page, `per_page` up to 100), newest first, with roles and permissions.
     */
    #[Response(status: 200, description: 'Paginated `SystemUserResource` collection.')]
    #[Response(status: 403, description: 'Missing the `system_users.view_any` permission.')]
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

    /**
     * Create a system user.
     *
     * **Request body:** `name`, `email`, `password` + `password_confirmation` (min 8), optional `status` (default `active`) and `roles` (role names; needs `system_users.assign_roles`).
     */
    #[Response(status: 201, description: 'The created `SystemUserResource`.')]
    #[Response(status: 403, description: 'Missing the `system_users.create` (or `assign_roles`) permission, or granting `super_admin` without being one.')]
    #[Response(status: 422, description: 'Validation failed.')]
    public function store(StoreSystemUserRequest $request): JsonResponse
    {
        $user = $this->systemUsers->create($request->user(), $request->validated());

        return SystemUserResource::make($user)->response()->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a system user.
     *
     * Deleted users are shown too (see `deleted_at`).
     */
    #[Response(status: 200, description: 'The `SystemUserResource`.')]
    #[Response(status: 403, description: 'Missing the `system_users.view` permission.')]
    #[Response(status: 404, description: 'No system user with that id.')]
    public function show(Request $request, SystemUser $systemUser): SystemUserResource
    {
        Gate::forUser($request->user())->authorize('view', $systemUser);

        return SystemUserResource::make($systemUser);
    }

    /**
     * Update a system user.
     *
     * Partial update. A new `password` (with `password_confirmation`) or `status: inactive` revokes the user's tokens. `roles` replaces the user's roles and needs `system_users.assign_roles`. Nobody changes their own `status` or `roles`.
     */
    #[Response(status: 200, description: 'The updated `SystemUserResource`.')]
    #[Response(status: 403, description: 'Missing the permission, or the change targets yourself or a super admin.')]
    #[Response(status: 404, description: 'No system user with that id.')]
    #[Response(status: 422, description: 'Validation failed, or the change would leave no active super admin.')]
    public function update(UpdateSystemUserRequest $request, SystemUser $systemUser): SystemUserResource
    {
        return SystemUserResource::make($this->systemUsers->update($request->user(), $systemUser, $request->validated()));
    }

    /**
     * Delete a system user.
     *
     * Soft delete; revokes every token. Nobody deletes themselves.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'Missing the `system_users.delete` permission, or the target is yourself.')]
    #[Response(status: 404, description: 'No system user with that id.')]
    #[Response(status: 422, description: 'The user is the last active super admin.')]
    public function destroy(Request $request, SystemUser $systemUser): \Illuminate\Http\Response
    {
        Gate::forUser($request->user())->authorize('delete', $systemUser);

        $this->systemUsers->delete($systemUser);

        return response()->noContent();
    }

    /**
     * Restore a deleted system user.
     */
    #[Response(status: 200, description: 'The restored `SystemUserResource`.')]
    #[Response(status: 403, description: 'Missing the `system_users.restore` permission.')]
    #[Response(status: 404, description: 'No system user with that id.')]
    public function restore(Request $request, SystemUser $systemUser): SystemUserResource
    {
        Gate::forUser($request->user())->authorize('restore', $systemUser);

        return SystemUserResource::make($this->systemUsers->restore($systemUser));
    }
}

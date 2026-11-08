<?php

namespace Modules\Support\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Support\Http\Requests\Admin\StoreSystemRoleRequest;
use Modules\Support\Http\Requests\Admin\UpdateSystemRoleRequest;
use Modules\Support\Http\Resources\Admin\SystemRoleResource;
use Modules\Support\Models\SystemRole;
use Modules\Support\Services\SystemRoleService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Admin / System roles', description: 'Management of admin roles and their permissions. Requires a system user Bearer token with the matching `system_roles.*` permission. Protected roles (`super_admin`) are read-only, and nobody grants a permission they do not hold.')]
class SystemRoleController extends Controller
{
    public function __construct(private readonly SystemRoleService $systemRoles) {}

    /**
     * List system roles.
     *
     * Every role, ordered by id, with its permissions and `users_count`.
     */
    #[Response(status: 200, description: '`SystemRoleResource` collection.')]
    #[Response(status: 403, description: 'Missing the `system_roles.view_any` permission.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', SystemRole::class);

        return SystemRoleResource::collection(
            SystemRole::query()->with('permissions')->withCount('users')->orderBy('id')->get()
        );
    }

    /**
     * Create a system role.
     *
     * **Request body:** `name` (snake_case, unique), optional `description` and `permissions` (permission names).
     */
    #[Response(status: 201, description: 'The created `SystemRoleResource`.')]
    #[Response(status: 403, description: 'Missing the `system_roles.create` permission.')]
    #[Response(status: 422, description: 'Validation failed, or a permission you do not hold.')]
    public function store(StoreSystemRoleRequest $request): JsonResponse
    {
        $role = $this->systemRoles->create($request->user(), $request->validated());

        return SystemRoleResource::make($role->load('permissions')->loadCount('users'))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Show a system role.
     */
    #[Response(status: 200, description: 'The `SystemRoleResource`.')]
    #[Response(status: 403, description: 'Missing the `system_roles.view` permission.')]
    #[Response(status: 404, description: 'No system role with that id.')]
    public function show(Request $request, SystemRole $systemRole): SystemRoleResource
    {
        Gate::forUser($request->user())->authorize('view', $systemRole);

        return SystemRoleResource::make($systemRole->load('permissions')->loadCount('users'));
    }

    /**
     * Update a system role.
     *
     * Partial update; `permissions` replaces the role's permissions.
     */
    #[Response(status: 200, description: 'The updated `SystemRoleResource`.')]
    #[Response(status: 403, description: 'Missing the `system_roles.update` permission, or the role is protected.')]
    #[Response(status: 404, description: 'No system role with that id.')]
    #[Response(status: 422, description: 'Validation failed, or a permission you do not hold.')]
    public function update(UpdateSystemRoleRequest $request, SystemRole $systemRole): SystemRoleResource
    {
        $role = $this->systemRoles->update($request->user(), $systemRole, $request->validated());

        return SystemRoleResource::make($role->load('permissions')->loadCount('users'));
    }

    /**
     * Delete a system role.
     *
     * Refused (422) while system users still hold it.
     */
    #[Response(status: 204, description: 'Deleted; no content.')]
    #[Response(status: 403, description: 'Missing the `system_roles.delete` permission, or the role is protected.')]
    #[Response(status: 404, description: 'No system role with that id.')]
    #[Response(status: 422, description: 'The role is still assigned.')]
    public function destroy(Request $request, SystemRole $systemRole): \Illuminate\Http\Response
    {
        Gate::forUser($request->user())->authorize('delete', $systemRole);

        $this->systemRoles->delete($systemRole);

        return response()->noContent();
    }
}

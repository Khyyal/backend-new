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
use Modules\Support\Http\Requests\Admin\StoreSystemRoleRequest;
use Modules\Support\Http\Requests\Admin\UpdateSystemRoleRequest;
use Modules\Support\Http\Resources\Admin\SystemRoleResource;
use Modules\Support\Models\SystemRole;
use Modules\Support\Services\SystemRoleService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Admin / System roles', 'Admin roles and their permissions. Requires the matching `system_roles.*` permission. Protected roles (`super_admin`) are read-only, and nobody grants a permission they do not hold.')]
class SystemRoleController extends Controller
{
    public function __construct(private readonly SystemRoleService $systemRoles) {}

    #[Endpoint('List system roles', 'Every role, ordered by ID, with permissions and `users_count`. Not paginated.')]
    #[ResponseFromApiResource(SystemRoleResource::class, SystemRole::class, status: 200, description: 'All roles.', collection: true, with: ['permissions'], withCount: ['users'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_roles.view_any`.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAny', SystemRole::class);

        return SystemRoleResource::collection(
            SystemRole::query()->with('permissions')->withCount('users')->orderBy('id')->get()
        );
    }

    #[Endpoint('Create a system role')]
    #[ResponseFromApiResource(SystemRoleResource::class, SystemRole::class, status: 201, description: 'The created role.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_roles.create`.')]
    #[ApiResponse(['message' => 'The name has already been taken.', 'errors' => ['name' => ['The name has already been taken.']]], 422, 'Validation failed, or a permission you do not hold.')]
    public function store(StoreSystemRoleRequest $request): JsonResponse
    {
        $role = $this->systemRoles->create($request->user(), $request->validated());

        return SystemRoleResource::make($role->load('permissions')->loadCount('users'))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    #[Endpoint('Show a system role')]
    #[UrlParam('systemRole', 'integer', 'System role ID.', example: 2)]
    #[ResponseFromApiResource(SystemRoleResource::class, SystemRole::class, status: 200, description: 'The role.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_roles.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No role with that ID.')]
    public function show(Request $request, SystemRole $systemRole): SystemRoleResource
    {
        Gate::forUser($request->user())->authorize('view', $systemRole);

        return SystemRoleResource::make($systemRole->load('permissions')->loadCount('users'));
    }

    #[Endpoint('Update a system role', 'Partial update; `permissions` replaces the role\'s permissions.')]
    #[UrlParam('systemRole', 'integer', 'System role ID.', example: 2)]
    #[ResponseFromApiResource(SystemRoleResource::class, SystemRole::class, status: 200, description: 'The updated role.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_roles.update`, or the role is protected.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No role with that ID.')]
    #[ApiResponse(['message' => 'You cannot grant permissions you do not hold.', 'errors' => ['permissions' => ['You cannot grant permissions you do not hold.']]], 422, 'Validation failed, or a permission you do not hold.')]
    public function update(UpdateSystemRoleRequest $request, SystemRole $systemRole): SystemRoleResource
    {
        $role = $this->systemRoles->update($request->user(), $systemRole, $request->validated());

        return SystemRoleResource::make($role->load('permissions')->loadCount('users'));
    }

    #[Endpoint('Delete a system role', 'Refused while system users still hold it.')]
    #[UrlParam('systemRole', 'integer', 'System role ID.', example: 2)]
    #[ApiResponse(status: 204, description: 'Deleted. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_roles.delete`, or the role is protected.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No role with that ID.')]
    #[ApiResponse(['message' => 'The role is still assigned to system users.', 'errors' => ['role' => ['The role is still assigned to system users.']]], 422, 'The role is still assigned.')]
    public function destroy(Request $request, SystemRole $systemRole): Response
    {
        Gate::forUser($request->user())->authorize('delete', $systemRole);

        $this->systemRoles->delete($systemRole);

        return response()->noContent();
    }
}

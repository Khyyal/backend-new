<?php

namespace Modules\Support\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemRole;

#[Group('Admin / System roles', 'Admin roles and permissions.')]
class SystemPermissionController extends Controller
{
    #[Endpoint('List permissions', 'Every permission a role can hold, grouped by resource (the part before the dot). Requires `system_roles.view_any`.')]
    #[ApiResponse(['data' => [['group' => 'centers', 'permissions' => ['centers.view_any', 'centers.view', 'centers.create']], ['group' => 'system_users', 'permissions' => ['system_users.view_any', 'system_users.view']]]], 200, 'Grouped permission names.')]
    #[ResponseField('data[].group', 'string', 'Resource name.', example: 'centers')]
    #[ResponseField('data[].permissions', 'string[]', 'Permission names (`resource.action`).', example: ['centers.view_any', 'centers.view'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `system_roles.view_any`.')]
    public function __invoke(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SystemRole::class);

        $groups = collect(SystemPermission::all())
            ->groupBy(fn (string $name): string => explode('.', $name)[0])
            ->map(fn ($permissions, string $group): array => ['group' => $group, 'permissions' => $permissions->values()])
            ->values();

        return response()->json(['data' => $groups]);
    }
}

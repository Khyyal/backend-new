<?php

namespace Modules\Support\Http\Controllers\Admin;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemRole;

#[Group(name: 'Admin / System roles')]
class SystemPermissionController extends Controller
{
    /**
     * List system permissions.
     *
     * Every permission a role can hold, grouped by resource (the part before the dot): `data: [{group, permissions: [name]}]`.
     */
    #[Response(status: 200, description: 'Permission names grouped by resource.')]
    #[Response(status: 403, description: 'Missing the `system_roles.view_any` permission.')]
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

<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * @mixin Center
 */
class CenterWithRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user('center_user');

        $role = null;
        if ($user instanceof User) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($this->id);
            $role = $user->getRoleNames()->first() ?: null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $role,
        ];
    }
}

<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\SystemRole;

/**
 * A system role with its permission names; protected roles cannot be edited or deleted.
 *
 * @property SystemRole $resource
 */
class SystemRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->resource;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_protected' => $role->isProtected(),
            'permissions' => $this->whenLoaded('permissions', fn () => $role->permissions->pluck('name')->values()),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ];
    }
}

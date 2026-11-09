<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
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
    #[ResponseField('id', 'integer', 'Role ID.', example: 2)]
    #[ResponseField('name', 'string', 'Unique snake_case role name.', example: 'support_agent')]
    #[ResponseField('description', 'string', 'Free-text description.', nullable: true, example: 'Handles client support tickets.')]
    #[ResponseField('is_protected', 'boolean', 'Protected roles (`super_admin`) cannot be edited or deleted.', example: false)]
    #[ResponseField('permissions', 'string[]', 'Permission names (`resource.action`) held by the role.', required: false, example: ['centers.view_any', 'centers.view'])]
    #[ResponseField('users_count', 'integer', 'System users holding the role.', required: false, example: 3)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
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

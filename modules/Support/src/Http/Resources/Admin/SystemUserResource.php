<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemRole;
use Modules\Support\Models\SystemUser;

/**
 * @property SystemUser $resource
 */
class SystemUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'System user ID.', example: 2)]
    #[ResponseField('name', 'string', 'Display name.', example: 'Sara Admin')]
    #[ResponseField('email', 'string', 'Login email.', example: 'sara@khyyal.com')]
    #[ResponseField('status', 'string', 'Inactive users cannot log in.', enum: ActivationStatus::class, example: 'active')]
    #[ResponseField('roles', 'string[]', 'Role names.', example: ['support_agent'])]
    #[ResponseField('permissions', 'string[]', 'Effective permissions: the union of all role permissions.', example: ['centers.view_any', 'centers.view'])]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('deleted_at', 'string', 'Soft-delete time (ISO-8601); null when not deleted.', nullable: true, example: 'No-example')]
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('roles.permissions');

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'status' => $this->resource->status->value,
            'roles' => $this->resource->roles->pluck('name')->values(),
            'permissions' => $this->resource->roles
                ->flatMap(fn (SystemRole $role) => $role->permissions->pluck('name'))
                ->unique()
                ->values(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'deleted_at' => $this->resource->deleted_at?->toIso8601String(),
        ];
    }
}

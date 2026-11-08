<?php

namespace Modules\Support\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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

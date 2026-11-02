<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;

/**
 * A user of a center, loaded through `Center::users()` so the assignment pivot
 * is present. The primary user is reported with every center permission.
 *
 * @mixin User
 */
class CenterMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Center $center */
        $center = $request->route('center');
        $isPrimary = (bool) $this->pivot->is_primary;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'is_primary' => $isPrimary,
            'status' => $this->pivot->status,
            'joined_at' => $this->pivot->joined_at?->toDateString(),
            'trainer' => $this->pivot->trainer === null ? null : [
                'id' => $this->pivot->trainer->id,
                'name' => $this->pivot->trainer->name,
            ],
            'permissions' => $isPrimary
                ? CenterUserPermission::all()
                : $this->resource->centerPermissionNames($center),
        ];
    }
}

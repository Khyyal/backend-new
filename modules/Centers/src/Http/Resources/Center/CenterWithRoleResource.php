<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * @mixin Center
 */
class CenterWithRoleResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Center ID.', example: 8)]
    #[ResponseField('name', 'string', 'Center name.', example: 'Al Fursan Stables')]
    #[ResponseField('state', 'string', 'Center account state.', enum: CenterState::class, example: 'active')]
    #[ResponseField('role', 'string', 'The user\'s role on this center (e.g. `owner`); null when none.', nullable: true, example: 'owner')]
    #[ResponseField('is_primary', 'boolean', 'True for the primary (owner) user; primary users need their password on `POST /centers/{center}/access`.', example: true)]
    public function toArray(Request $request): array
    {
        $user = $request->user('center_user');

        $role = null;
        if ($user instanceof User) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($this->id);
            $user->unsetRelation('roles');
            $role = $user->getRoleNames()->first() ?: null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'state' => $this->state,
            'role' => $role,
            'is_primary' => (bool) ($this->pivot?->is_primary ?? false),
        ];
    }
}

<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;

/**
 * A user of a center, loaded through `Center::users()` so the assignment pivot
 * is present. The primary user is reported with every center permission.
 *
 * @mixin User
 */
class CenterMemberResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Center user ID.', example: 15)]
    #[ResponseField('name', 'string', 'Full name.', example: 'Khalid Al-Harbi')]
    #[ResponseField('phone', 'string', 'Phone number used for OTP login.', example: '+966500000002')]
    #[ResponseField('is_primary', 'boolean', 'True for the center owner.', example: false)]
    #[ResponseField('status', 'string', 'Assignment status; inactive users cannot access the center.', enum: ActivationStatus::class, example: 'active')]
    #[ResponseField('joined_at', 'string', 'Assignment date, `Y-m-d`.', nullable: true, example: '2026-05-20')]
    #[ResponseField('trainer', 'object', 'Linked trainer of the center; null for the primary user.', nullable: true)]
    #[ResponseField('trainer.id', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseField('trainer.name', 'string', 'Trainer name.', example: 'Fahad')]
    #[ResponseField('permissions', 'string[]', 'Permissions on the center; the primary user holds all of them.', example: ['services.training.manage', 'purchases.training.view'])]
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

<?php

namespace Modules\Centers\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\CenterUser;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;

/**
 * A center's staff member as admins see it, with their assignment to the
 * center. Load it through `Center::users()` (with the pivot's trainer) on a
 * `{center}` route. The primary user is reported with every center permission.
 *
 * @property User $resource
 */
class CenterUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Center user ID.', example: 12)]
    #[ResponseField('name', 'string', 'Name.', example: 'Ahmed Saleh')]
    #[ResponseField('phone', 'string', 'Phone used for OTP login.', example: '0551234567')]
    #[ResponseField('is_primary', 'boolean', 'Whether this is the center\'s primary (owner) user.', example: true)]
    #[ResponseField('status', 'string', 'Assignment status in this center; inactive users cannot access it.', enum: ActivationStatus::class, example: 'active')]
    #[ResponseField('joined_at', 'string', 'Assignment date, `Y-m-d`.', nullable: true, example: '2026-05-20')]
    #[ResponseField('phone_verified_at', 'string', 'When the phone was verified (ISO-8601).', nullable: true, example: '2026-10-01T08:00:00+00:00')]
    #[ResponseField('trainer', 'object', 'Linked trainer of the center; null for the primary user.', nullable: true)]
    #[ResponseField('trainer.id', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseField('trainer.name', 'string', 'Trainer name.', example: 'Fahad')]
    #[ResponseField('permissions', 'string[]', 'Permissions on the center; the primary user holds all of them.', example: ['services.training.manage', 'purchases.training.view'])]
    public function toArray(Request $request): array
    {
        /** @var CenterUser|null $assignment */
        $assignment = $this->resource->getRelationValue('pivot');
        $isPrimary = (bool) $assignment?->is_primary;

        /** @var Center $center */
        $center = $request->route('center');
        $trainer = $assignment?->trainer;

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'phone' => $this->resource->phone,
            'is_primary' => $isPrimary,
            'status' => $assignment?->status?->value,
            'joined_at' => $assignment?->joined_at?->toDateString(),
            'phone_verified_at' => $this->resource->phone_verified_at === null
                ? null
                : Carbon::parse($this->resource->phone_verified_at)->toIso8601String(),
            'trainer' => $trainer === null ? null : [
                'id' => $trainer->id,
                'name' => $trainer->name,
            ],
            'permissions' => $isPrimary
                ? CenterUserPermission::all()
                : $this->resource->centerPermissionNames($center),
        ];
    }
}

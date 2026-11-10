<?php

namespace Modules\Centers\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\CenterUser;
use Modules\Centers\Models\User;

/**
 * A center's staff member as admins see it, with their assignment to the center.
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
    #[ResponseField('status', 'string', 'Assignment status in this center.', example: 'active')]
    #[ResponseField('phone_verified_at', 'string', 'When the phone was verified (ISO-8601).', nullable: true, example: '2026-10-01T08:00:00+00:00')]
    public function toArray(Request $request): array
    {
        /** @var CenterUser|null $assignment */
        $assignment = $this->resource->getRelationValue('pivot');

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'phone' => $this->resource->phone,
            'is_primary' => (bool) $assignment?->is_primary,
            'status' => $assignment?->status?->value,
            'phone_verified_at' => $this->resource->phone_verified_at === null
                ? null
                : Carbon::parse($this->resource->phone_verified_at)->toIso8601String(),
        ];
    }
}

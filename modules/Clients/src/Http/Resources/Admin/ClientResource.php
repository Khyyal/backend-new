<?php

namespace Modules\Clients\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Support\Enums\Gender;
use Modules\Support\Http\Resources\CityResource;

/**
 * A client as seen by system users, with activity counts.
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('first_name', 'string', 'First name.', nullable: true, example: 'Noura')]
    #[ResponseField('last_name', 'string', 'Last name.', nullable: true, example: 'Al-Qahtani')]
    #[ResponseField('full_name', 'string', 'First and last name.', example: 'Noura Al-Qahtani')]
    #[ResponseField('phone_number', 'string', 'Phone used for OTP login.', example: '+966500000003')]
    #[ResponseField('gender', 'string', 'Gender; null when unknown.', nullable: true, enum: Gender::class, example: 'female')]
    #[ResponseField('city_id', 'integer', 'City ID; null when none.', nullable: true, example: 1)]
    #[ResponseField('city', 'object', 'The city (`CityResource`); present when loaded, null when the client has none.', required: false, nullable: true)]
    #[ResponseField('city.id', 'integer', 'City ID.', example: 1)]
    #[ResponseField('city.name', 'string', 'City name in the request locale.', example: 'Riyadh')]
    #[ResponseField('city.radius', 'integer', 'Coverage radius in km.', example: 40)]
    #[ResponseField('needs_onboarding', 'boolean', 'True while a name is missing.', example: false)]
    #[ResponseField('centers_count', 'integer', 'Centers the client belongs to.', required: false, example: 2)]
    #[ResponseField('reservations_count', 'integer', 'Reservations made.', required: false, example: 9)]
    #[ResponseField('packages_count', 'integer', 'Session packages bought.', required: false, example: 1)]
    #[ResponseField('memberships_count', 'integer', 'Memberships bought.', required: false, example: 0)]
    #[ResponseField('created_at', 'string', 'Registration time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'phone_number' => $this->phone_number,
            'gender' => $this->gender,
            'city_id' => $this->city_id,
            'city' => new CityResource($this->whenLoaded('city')),
            'needs_onboarding' => $this->needs_onboarding,
            'centers_count' => $this->whenCounted('centers'),
            'reservations_count' => $this->whenCounted('reservations'),
            'packages_count' => $this->whenCounted('packages'),
            'memberships_count' => $this->whenCounted('memberships'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace Modules\Clients\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Http\Resources\CityResource;

/**
 * A client as seen by system users, with activity counts.
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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

<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservations\Models\ClientMembership;

/**
 * @property ClientMembership $resource
 */
class ClientMembershipResource extends JsonResource
{
    /**
     * Free visits already used today, when counted by the caller.
     */
    private ?int $visitsUsedToday = null;

    public function withVisitsUsedToday(?int $visitsUsedToday): static
    {
        $this->visitsUsedToday = $visitsUsedToday;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $membership = $this->resource;

        return [
            'id' => $membership->id,
            'status' => $membership->status->value,
            'service' => $this->whenLoaded('service', fn (): array => [
                'id' => $membership->service->id,
                'name' => $membership->service->getTranslations('name'),
                'color' => $membership->membership?->color,
            ]),
            'membership_id' => $membership->membership_id,
            'price' => $membership->price,
            'duration' => $membership->duration,
            'visit_service' => $this->whenLoaded('visitService', fn (): ?array => $membership->visitService === null ? null : [
                'id' => $membership->visitService->id,
                'name' => $membership->visitService->getTranslations('name'),
            ]),
            'max_visits_per_day' => $membership->max_visits_per_day,
            'visits_left_today' => $this->when(
                $this->visitsUsedToday !== null && $membership->grantsVisits(),
                fn (): int => max(0, $membership->max_visits_per_day - $this->visitsUsedToday),
            ),
            'packages' => ClientPackageResource::collection($this->whenLoaded('packages')),
            'expires_at' => $membership->expires_at?->toIso8601String(),
            'is_usable' => $membership->isUsable(),
            'created_at' => $membership->created_at?->toIso8601String(),
        ];
    }
}

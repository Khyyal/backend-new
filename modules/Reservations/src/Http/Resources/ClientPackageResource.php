<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservations\Models\ClientPackage;

/**
 * @property ClientPackage $resource
 */
class ClientPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $package = $this->resource;

        return [
            'id' => $package->id,
            'status' => $package->status->value,
            'service' => $this->whenLoaded('service', fn (): array => [
                'id' => $package->service->id,
                'type' => $package->service->type->value,
                'name' => $package->service->getTranslations('name'),
            ]),
            'service_package_id' => $package->service_package_id,
            'duration_minutes' => $this->whenLoaded('priceOption', fn (): ?int => $package->priceOption?->quantity),
            'total_sessions' => $package->total_sessions,
            'remaining_sessions' => $package->remaining_sessions,
            'price' => (float) $package->price,
            'expires_at' => $package->expires_at?->toIso8601String(),
            'is_usable' => $package->isUsable(),
            'created_at' => $package->created_at?->toIso8601String(),
        ];
    }
}

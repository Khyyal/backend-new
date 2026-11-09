<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\ClientPackage;

/**
 * @property ClientPackage $resource
 */
class ClientPackageResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Client package ID; book sessions with `POST /api/v1/clients/packages/{package}/reservations`.', example: 41)]
    #[ResponseField('status', 'string', '`pending` until paid, then `active`, `exhausted`, `expired` or `cancelled`.', enum: ClientPackageStatus::class, example: 'active')]
    #[ResponseField('service', 'object', 'The service (`id`, `type`, `name` by locale). Present when loaded.', required: false, example: ['id' => 31, 'type' => 'training', 'name' => ['ar' => 'تدريب خاص', 'en' => 'Private training']])]
    #[ResponseField('service_package_id', 'integer', 'The package bought.', example: 5)]
    #[ResponseField('duration_minutes', 'integer', 'Session length in minutes. Present when loaded.', required: false, nullable: true, example: 60)]
    #[ResponseField('total_sessions', 'integer', 'Sessions bought.', example: 8)]
    #[ResponseField('remaining_sessions', 'integer', 'Sessions left.', example: 5)]
    #[ResponseField('price', 'integer', 'Price paid in halalas.', example: 100000)]
    #[ResponseField('expires_at', 'string', 'Expiry (ISO-8601); null until paid.', nullable: true, example: '2026-09-01T00:00:00+00:00')]
    #[ResponseField('is_usable', 'boolean', 'Active, not expired and with sessions left.', example: true)]
    #[ResponseField('created_at', 'string', 'Purchase time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
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
            'price' => $package->price,
            'expires_at' => $package->expires_at?->toIso8601String(),
            'is_usable' => $package->isUsable(),
            'created_at' => $package->created_at?->toIso8601String(),
        ];
    }
}

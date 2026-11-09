<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ClientPackageStatus;

/**
 * A client package as center staff see it: adds the owner.
 */
class CenterClientPackageResource extends ClientPackageResource
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
    #[ResponseField('client', 'object', 'The client (`id`, `full_name`, `phone_number`). Present when loaded.', required: false, example: ['id' => 77, 'full_name' => 'Noura Al-Qahtani', 'phone_number' => '+966500000003'])]
    public function toArray(Request $request): array
    {
        $package = $this->resource;

        return [
            ...parent::toArray($request),
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $package->client->id,
                'full_name' => $package->client->full_name,
                'phone_number' => $package->client->phone_number,
            ]),
        ];
    }
}

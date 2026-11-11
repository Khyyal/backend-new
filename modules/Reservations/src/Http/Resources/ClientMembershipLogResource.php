<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Clients\Models\Client;
use Modules\Reservations\Enums\ClientMembershipLogType;
use Modules\Reservations\Models\ClientMembershipLog;
use Modules\Support\Models\SystemUser;

/**
 * An entry of a membership's log.
 *
 * @property ClientMembershipLog $resource
 */
class ClientMembershipLogResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Log entry ID.', example: 12)]
    #[ResponseField('type', 'string', '`invite` (the member brought a guest) or `check_in` (recorded by the center).', enum: ClientMembershipLogType::class, example: 'invite')]
    #[ResponseField('notes', 'string', 'Staff notes of a check-in.', nullable: true, example: 'Came with two friends.')]
    #[ResponseField('guest', 'object', 'The invited client (`id`, `full_name`, `phone_number`); null for check-ins. Present when loaded.', required: false, nullable: true, example: ['id' => 78, 'full_name' => 'Sara Al-Otaibi', 'phone_number' => '+966500000004'])]
    #[ResponseField('reservation', 'object', 'The visit created for the guest (`id`, `code`, `date`, `status`); null for check-ins or a deleted reservation. Present when loaded.', required: false, nullable: true, example: ['id' => 320, 'code' => 'K7QX2M', 'date' => '2026-06-06', 'status' => 'confirmed'])]
    #[ResponseField('actor', 'object', 'Who did it (`type`: `client`, `center_user` or `system_user`; `id`; `name`); null when unknown. Present when loaded.', required: false, nullable: true, example: ['type' => 'client', 'id' => 77, 'name' => 'Noura Al-Qahtani'])]
    #[ResponseField('created_at', 'string', 'When it was logged (ISO-8601).', example: '2026-06-06T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        $log = $this->resource;

        return [
            'id' => $log->id,
            'type' => $log->type->value,
            'notes' => $log->notes,
            'guest' => $this->whenLoaded('guest', fn (): ?array => $log->guest === null ? null : [
                'id' => $log->guest->id,
                'full_name' => $log->guest->full_name,
                'phone_number' => $log->guest->phone_number,
            ]),
            'reservation' => $this->whenLoaded('reservation', fn (): ?array => $log->reservation === null ? null : [
                'id' => $log->reservation->id,
                'code' => $log->reservation->code,
                'date' => $log->reservation->date->toDateString(),
                'status' => $log->reservation->status->value,
            ]),
            'actor' => $this->whenLoaded('actor', fn (): ?array => $log->actor === null ? null : [
                'type' => match (true) {
                    $log->actor instanceof Client => 'client',
                    $log->actor instanceof SystemUser => 'system_user',
                    default => 'center_user',
                },
                'id' => $log->actor->getKey(),
                'name' => $log->actor instanceof Client ? $log->actor->full_name : $log->actor->name,
            ]),
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}

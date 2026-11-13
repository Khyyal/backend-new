<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Horse;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property Horse $resource
 */
class HorseResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Horse ID.', example: 5)]
    #[ResponseField('name', 'string', 'Horse name.', example: 'Najm')]
    #[ResponseField('room', 'integer', 'Room (stall) number.', example: 12)]
    #[ResponseField('owner_type', 'string', '`center` when the center owns the horse, `client` when a client does.', enum: ['center', 'client'], example: 'client')]
    #[ResponseField('client', 'object', 'Owning client; null for center-owned horses.', nullable: true)]
    #[ResponseField('client.id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('client.first_name', 'string', 'First name.', nullable: true, example: 'Sara')]
    #[ResponseField('client.last_name', 'string', 'Last name.', nullable: true, example: 'Ali')]
    #[ResponseField('client.phone_number', 'string', 'Phone number.', example: '966500000000')]
    #[ResponseField('image', 'object', 'Horse image (`MediaResource`); null when none.', nullable: true)]
    #[ResponseField('image.id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('image.url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/najm.jpg')]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-11-13T09:30:00.000000Z')]
    public function toArray(Request $request): array
    {
        $client = $this->resource->client;
        $image = $this->resource->media->firstWhere('collection_name', 'image');

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'room' => $this->resource->room,
            'owner_type' => $this->resource->isOwnedByCenter() ? 'center' : 'client',
            'client' => $client === null ? null : [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'phone_number' => $client->phone_number,
            ],
            'image' => $image === null ? null : new MediaResource($image),
            'created_at' => $this->resource->created_at,
        ];
    }
}

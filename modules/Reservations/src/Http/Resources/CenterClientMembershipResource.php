<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;

/**
 * A client membership as center staff see it: adds the owner.
 */
class CenterClientMembershipResource extends ClientMembershipResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->resource;

        return [
            ...parent::toArray($request),
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $membership->client->id,
                'full_name' => $membership->client->full_name,
                'phone_number' => $membership->client->phone_number,
            ]),
        ];
    }
}

<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;

/**
 * A client package as center staff see it: adds the owner.
 */
class CenterClientPackageResource extends ClientPackageResource
{
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

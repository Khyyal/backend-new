<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Service;

/**
 * A service in search results.
 *
 * @property Service $resource
 */
class ServiceSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = $this->resource;

        return [
            'id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'description' => $service->description,
            'type' => $service->type->value,
            'center' => [
                'id' => $service->center->id,
                'name' => $service->center->name,
                'slug' => $service->center->slug,
            ],
        ];
    }
}

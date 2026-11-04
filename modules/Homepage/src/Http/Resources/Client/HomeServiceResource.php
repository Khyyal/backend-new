<?php

namespace Modules\Homepage\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Service;

/**
 * A service card on the home page. `start_price` and `display_price` are minor units;
 * `display_price` is set when an automatic offer lowers the start price right now.
 *
 * @property Service $resource
 */
class HomeServiceResource extends JsonResource
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $prices = [];

    /**
     * @param  array<int, array<string, mixed>>  $prices  display prices keyed by service id
     */
    public function withPrices(array $prices): static
    {
        $this->prices = $prices;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $service = $this->resource;

        return [
            'id' => $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'type' => $service->type->value,
            'center' => $this->whenLoaded('center', fn () => [
                'id' => $service->center->id,
                'name' => $service->center->name,
                'slug' => $service->center->slug,
            ]),
            'cover' => $this->whenLoaded('media', fn () => $service->getFirstMedia('cover')?->getFullUrl('cover-thumb')),
            'start_price' => $service->start_price !== null ? (int) $service->start_price : null,
            'display_price' => $this->prices[$service->id] ?? null,
        ];
    }
}

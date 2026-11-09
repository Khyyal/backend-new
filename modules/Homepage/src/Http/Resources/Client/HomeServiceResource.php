<?php

namespace Modules\Homepage\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
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

    #[ResponseField('id', 'integer', 'Service ID.', example: 31)]
    #[ResponseField('slug', 'string', 'Slug.', example: 'beginner-ride')]
    #[ResponseField('name', 'string', 'Name in the request locale.', example: 'Beginner ride')]
    #[ResponseField('type', 'string', 'Service type.', example: 'recreation_riding')]
    #[ResponseField('center', 'object', 'Center (`id`, `name`, `slug`).', required: false, example: ['id' => 8, 'name' => 'Al Fursan Stables', 'slug' => 'al-fursan-stables'])]
    #[ResponseField('cover', 'string', 'Cover thumbnail URL.', required: false, nullable: true, example: 'No-example')]
    #[ResponseField('start_price', 'integer', 'Lowest price in halalas.', nullable: true, example: 15000)]
    #[ResponseField('display_price', 'object', 'Set only while an automatic offer lowers the start price: `original`, `discounted`, `discount_amount` (halalas), `badge`.', nullable: true, example: ['original' => 15000, 'discounted' => 12750, 'discount_amount' => 2250, 'badge' => '-15%'])]
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

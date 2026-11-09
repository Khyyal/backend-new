<?php

namespace Modules\Homepage\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Promotions\Models\Campaign;

/**
 * A running campaign as clients see it.
 *
 * @property Campaign $resource
 */
class HomeCampaignResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Campaign ID.', example: 3)]
    #[ResponseField('slug', 'string', 'Slug.', example: 'summer-2026')]
    #[ResponseField('name', 'string', 'Name in the request locale.', example: 'Summer 2026')]
    #[ResponseField('description', 'string', 'Description in the request locale.', nullable: true, example: 'Summer deals across all centers.')]
    #[ResponseField('starts_at', 'string', 'Start (ISO-8601).', nullable: true, example: '2026-06-01T00:00:00+00:00')]
    #[ResponseField('ends_at', 'string', 'End (ISO-8601).', nullable: true, example: '2026-08-31T23:59:59+00:00')]
    #[ResponseField('cover', 'string', 'Cover thumbnail URL.', required: false, nullable: true, example: 'No-example')]
    public function toArray(Request $request): array
    {
        $campaign = $this->resource;

        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'cover' => $this->whenLoaded('media', fn () => $campaign->getFirstMedia('cover')?->getFullUrl('cover-thumb')),
        ];
    }
}

<?php

namespace Modules\Homepage\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotions\Models\Campaign;

/**
 * A running campaign as clients see it.
 *
 * @property Campaign $resource
 */
class HomeCampaignResource extends JsonResource
{
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

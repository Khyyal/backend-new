<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotions\Models\Campaign;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property Campaign $resource
 */
class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $campaign = $this->resource;

        return [
            'id' => $campaign->id,
            'name' => $campaign->getTranslations('name'),
            'slug' => $campaign->slug,
            'description' => $campaign->getTranslations('description'),
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'status' => $campaign->status->value,
            'cover' => $this->whenLoaded('media', fn () => ($media = $campaign->getFirstMedia('cover')) !== null ? new MediaResource($media) : null),
            'promotions_count' => $this->whenCounted('promotions'),
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
        ];
    }
}

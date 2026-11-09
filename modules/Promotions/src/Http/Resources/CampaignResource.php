<?php

namespace Modules\Promotions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Promotions\Enums\CampaignStatus;
use Modules\Promotions\Models\Campaign;
use Modules\Support\Http\Resources\MediaResource;

/**
 * @property Campaign $resource
 */
class CampaignResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Campaign ID.', example: 3)]
    #[ResponseField('name', 'object', 'Campaign name. Translations keyed by locale.', required: true, nullable: false)]
    #[ResponseField('name.en', 'string', 'English text.', required: false, nullable: true, example: 'Summer offer')]
    #[ResponseField('name.ar', 'string', 'Arabic text.', required: false, nullable: true, example: 'عرض الصيف')]
    #[ResponseField('slug', 'string', 'Unique URL slug.', example: 'summer-2026')]
    #[ResponseField('description', 'object', 'Campaign description. Translations keyed by locale.', required: true, nullable: false)]
    #[ResponseField('description.en', 'string', 'English text.', required: false, nullable: true, example: 'Summer offer')]
    #[ResponseField('description.ar', 'string', 'Arabic text.', required: false, nullable: true, example: 'عرض الصيف')]
    #[ResponseField('starts_at', 'string', 'Start (ISO-8601, UTC). Null means no start bound.', nullable: true, example: '2026-06-01T00:00:00+00:00')]
    #[ResponseField('ends_at', 'string', 'End (ISO-8601, UTC). Null means open-ended.', nullable: true, example: '2026-08-31T23:59:59+00:00')]
    #[ResponseField('status', 'string', 'Campaign status.', enum: CampaignStatus::class, example: 'active')]
    #[ResponseField('cover', 'object', 'Cover image (`MediaResource`). Present only when media is loaded; null when there is no cover.', required: false, nullable: true)]
    #[ResponseField('cover.id', 'string', 'Media UUID.', example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
    #[ResponseField('cover.status', 'string', 'Media lifecycle state.', enum: ['temporary', 'attached'], example: 'attached')]
    #[ResponseField('cover.url', 'string', 'Public URL of the original file.', example: 'https://cdn.khyyal.com/media/12/cover.jpg')]
    #[ResponseField('cover.filename', 'string', 'Stored file name.', example: 'cover.jpg')]
    #[ResponseField('cover.mime_type', 'string', 'MIME type.', example: 'image/jpeg')]
    #[ResponseField('cover.size', 'integer', 'File size in bytes.', example: 184233)]
    #[ResponseField('promotions_count', 'integer', 'Number of promotions in the campaign. Present on list and show responses.', required: false, example: 4)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-21T11:00:00+00:00')]
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

<?php

namespace Modules\Centers\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Tag;

/**
 * A tag as admins manage it: both translations and how many centers use it.
 *
 * @property Tag $resource
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Tag ID.', example: 4)]
    #[ResponseField('name', 'object', 'Tag name keyed by locale.')]
    #[ResponseField('name.en', 'string', 'English name.', example: 'Family friendly')]
    #[ResponseField('name.ar', 'string', 'Arabic name.', example: 'مناسب للعائلات')]
    #[ResponseField('centers_count', 'integer', 'Centers using the tag.', required: false, example: 9)]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->getTranslations('name'),
            'centers_count' => $this->whenCounted('centers'),
        ];
    }
}

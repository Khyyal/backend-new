<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Centers\Models\Tag;

/**
 * A tag as center users see it, named in the request locale.
 *
 * @property Tag $resource
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Tag ID.', example: 4)]
    #[ResponseField('name', 'string', 'Tag name in the request locale.', example: 'Family friendly')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}

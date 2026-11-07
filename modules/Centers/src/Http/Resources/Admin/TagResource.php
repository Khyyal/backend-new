<?php

namespace Modules\Centers\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->getTranslations('name'),
            'centers_count' => $this->whenCounted('centers'),
        ];
    }
}

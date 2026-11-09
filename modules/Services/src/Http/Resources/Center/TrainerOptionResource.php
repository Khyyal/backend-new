<?php

namespace Modules\Services\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Models\Trainer;

/**
 * @property Trainer $resource
 */
class TrainerOptionResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Trainer ID.', example: 6)]
    #[ResponseField('name', 'string', 'Trainer name.', example: 'Fahad')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}

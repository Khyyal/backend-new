<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Services\Models\Trainer;

/**
 * Client-facing shape of a trainer of a training service.
 *
 * @property Trainer $resource
 */
class TrainerResource extends JsonResource
{
    /**
     * @var array<int, array{date: string, available: bool, hours: array<int, array<string, mixed>>}>|null
     */
    private ?array $availability = null;

    /**
     * @param  array<int, array{date: string, available: bool, hours: array<int, array<string, mixed>>}>|null  $availability
     */
    public function withAvailability(?array $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'availability' => $this->availability,
        ];
    }
}

<?php

namespace Modules\Clients\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A client with their purchase summary for one center. Wraps an aggregate row, not a model.
 */
class CenterClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => (int) $row->id,
            'full_name' => trim("{$row->first_name} {$row->last_name}"),
            'phone_number' => $row->phone_number,
            'first_purchase_at' => CarbonImmutable::parse($row->first_purchase_at)->toIso8601String(),
            'last_purchase_at' => CarbonImmutable::parse($row->last_purchase_at)->toIso8601String(),
            'purchases_count' => (int) $row->purchases_count,
        ];
    }
}

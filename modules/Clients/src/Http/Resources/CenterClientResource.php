<?php

namespace Modules\Clients\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

/**
 * A client with their purchase summary for one center (dates are null when they have no purchases). Wraps an aggregate row, not a model.
 */
class CenterClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('id', 'integer', 'Client ID.', example: 77)]
    #[ResponseField('full_name', 'string', 'First and last name (empty string before onboarding).', example: 'Noura Al-Qahtani')]
    #[ResponseField('phone_number', 'string', 'Phone.', example: '+966500000003')]
    #[ResponseField('first_purchase_at', 'string', 'First confirmed or completed purchase at the center (ISO-8601); null without purchases.', nullable: true, example: '2026-03-02T10:00:00+00:00')]
    #[ResponseField('last_purchase_at', 'string', 'Latest confirmed or completed purchase at the center (ISO-8601); null without purchases.', nullable: true, example: '2026-05-28T16:00:00+00:00')]
    #[ResponseField('purchases_count', 'integer', 'Confirmed and completed purchases at the center.', example: 4)]
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => (int) $row->id,
            'full_name' => trim("{$row->first_name} {$row->last_name}"),
            'phone_number' => $row->phone_number,
            'first_purchase_at' => $row->first_purchase_at ? CarbonImmutable::parse($row->first_purchase_at)->toIso8601String() : null,
            'last_purchase_at' => $row->last_purchase_at ? CarbonImmutable::parse($row->last_purchase_at)->toIso8601String() : null,
            'purchases_count' => (int) $row->purchases_count,
        ];
    }
}

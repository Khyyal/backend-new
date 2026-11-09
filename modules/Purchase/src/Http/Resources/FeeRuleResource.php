<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Models\FeeRule;

/**
 * @property FeeRule $resource
 */
class FeeRuleResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Fee rule ID.', example: 2)]
    #[ResponseField('center_id', 'integer', 'Center; null for the platform default.', nullable: true, example: 'No-example')]
    #[ResponseField('channel', 'string', 'Channel; null for the fallback rule.', nullable: true, enum: PurchaseChannel::class, example: 'khyyal_app')]
    #[ResponseField('percentage_bps', 'integer', 'Percentage of the purchase total in basis points (500 = 5%).', example: 500)]
    #[ResponseField('fixed_amount', 'integer', 'Fixed fee per purchase in halalas.', example: 0)]
    #[ResponseField('min_amount', 'integer', 'Minimum fee in halalas; null for none.', nullable: true, example: 100)]
    #[ResponseField('max_amount', 'integer', 'Maximum fee in halalas; null for none.', nullable: true, example: 'No-example')]
    #[ResponseField('is_active', 'boolean', 'Only active rules apply.', example: true)]
    #[ResponseField('created_at', 'string', 'Creation time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('updated_at', 'string', 'Last update time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    public function toArray(Request $request): array
    {
        $rule = $this->resource;

        return [
            'id' => $rule->id,
            'center_id' => $rule->center_id,
            'channel' => $rule->channel?->value,
            'percentage_bps' => $rule->percentage_bps,
            'fixed_amount' => $rule->fixed_amount,
            'min_amount' => $rule->min_amount,
            'max_amount' => $rule->max_amount,
            'is_active' => $rule->is_active,
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}

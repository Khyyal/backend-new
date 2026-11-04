<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Purchase\Models\FeeRule;

/**
 * @property FeeRule $resource
 */
class FeeRuleResource extends JsonResource
{
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

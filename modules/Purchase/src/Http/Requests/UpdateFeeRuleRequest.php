<?php

namespace Modules\Purchase\Http\Requests;

use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Purchase\Enums\PurchaseChannel;

#[BodyParam('center_id', 'integer', 'Center the rule applies to; null for the platform default.', required: false, nullable: true, example: 8)]
#[BodyParam('channel', 'string', 'Channel the rule applies to; null for the fallback rule. One rule per center + channel.', required: false, nullable: true, enum: PurchaseChannel::class, example: 'khyyal_app')]
#[BodyParam('percentage_bps', 'integer', 'Percentage in basis points, 0-10000.', required: false, example: 500)]
#[BodyParam('fixed_amount', 'integer', 'Fixed fee per purchase in halalas (min 0).', required: false, example: 0)]
#[BodyParam('min_amount', 'integer', 'Minimum fee in halalas; null for none.', required: false, nullable: true, example: 100)]
#[BodyParam('max_amount', 'integer', 'Maximum fee in halalas; null for none.', required: false, nullable: true, example: 5000)]
#[BodyParam('is_active', 'boolean', 'Whether it applies.', required: false, example: true)]
class UpdateFeeRuleRequest extends StoreFeeRuleRequest
{
    protected bool $isUpdate = true;
}

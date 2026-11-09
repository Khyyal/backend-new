<?php

namespace Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Models\FeeRule;

/**
 * A center and channel pair has at most one rule (null standing for the
 * platform default and the fallback), and `max_amount` is at least
 * `min_amount`. Both checks look at the rule as it will be saved.
 */
#[BodyParam('center_id', 'integer', 'Center the rule applies to; null for the platform default. Must be sent (may be null).', required: true, nullable: true, example: 8)]
#[BodyParam('channel', 'string', 'Channel the rule applies to; null for the fallback rule. Must be sent (may be null). One rule per center + channel.', required: true, nullable: true, enum: PurchaseChannel::class, example: 'khyyal_app')]
#[BodyParam('percentage_bps', 'integer', 'Percentage in basis points, 0-10000.', required: false, example: 500)]
#[BodyParam('fixed_amount', 'integer', 'Fixed fee per purchase in halalas (min 0).', required: false, example: 0)]
#[BodyParam('min_amount', 'integer', 'Minimum fee in halalas; null for none.', required: false, nullable: true, example: 100)]
#[BodyParam('max_amount', 'integer', 'Maximum fee in halalas; null for none.', required: false, nullable: true, example: 5000)]
#[BodyParam('is_active', 'boolean', 'Whether it applies.', required: false, example: true)]
class StoreFeeRuleRequest extends FormRequest
{
    protected bool $isUpdate = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $presence = $this->isUpdate ? 'sometimes' : 'present';

        return [
            'center_id' => [$presence, 'nullable', 'integer', 'exists:centers,id'],
            'channel' => [$presence, 'nullable', Rule::enum(PurchaseChannel::class)],
            'percentage_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'fixed_amount' => ['sometimes', 'integer', 'min:0'],
            'min_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $rule = $this->route('fee_rule');
                $current = $rule instanceof FeeRule ? $rule : null;
                $centerId = $this->has('center_id') ? $this->input('center_id') : $current?->center_id;
                $channel = $this->has('channel') ? $this->input('channel') : $current?->channel?->value;
                $minAmount = $this->has('min_amount') ? $this->input('min_amount') : $current?->min_amount;
                $maxAmount = $this->has('max_amount') ? $this->input('max_amount') : $current?->max_amount;

                $isTaken = FeeRule::query()
                    ->when($centerId === null, fn ($query) => $query->whereNull('center_id'), fn ($query) => $query->where('center_id', $centerId))
                    ->when($channel === null, fn ($query) => $query->whereNull('channel'), fn ($query) => $query->where('channel', $channel))
                    ->when($current !== null, fn ($query) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($isTaken) {
                    $validator->errors()->add('channel', __('validation.unique', ['attribute' => 'channel']));
                }

                if ($minAmount !== null && $maxAmount !== null && (int) $maxAmount < (int) $minAmount) {
                    $validator->errors()->add('max_amount', __('validation.gte.numeric', ['attribute' => 'max amount', 'value' => $minAmount]));
                }
            },
        ];
    }
}

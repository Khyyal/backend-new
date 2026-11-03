<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Enums\PromotionStacking;
use Modules\Promotions\Enums\PromotionTrigger;
use Modules\Promotions\Http\Requests\Concerns\ResolvesPromotionContext;
use Modules\Promotions\Models\Promotion;

/**
 * Amounts are minor units (halalas); a percentage `discount_value` is in basis
 * points (1250 = 12.5%). On the center API the promotion always belongs to
 * the route center and is funded by it, so ownership and funding fields are
 * prohibited there.
 */
class StorePromotionRequest extends FormRequest
{
    use ResolvesPromotionContext;

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
        $presence = $this->isUpdate ? 'sometimes' : 'required';
        $adminOnly = fn (array $rules): array => $this->isCenterContext() ? ['prohibited'] : $rules;

        return [
            'name' => [$presence, 'array'],
            'name.en' => [$presence, 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string', 'max:2000'],
            'description.ar' => ['nullable', 'string', 'max:2000'],
            'badge_label' => ['nullable', 'array'],
            'badge_label.en' => ['nullable', 'string', 'max:60'],
            'badge_label.ar' => ['nullable', 'string', 'max:60'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'campaign_id' => $adminOnly(['nullable', 'integer', 'exists:campaigns,id']),
            'center_id' => $adminOnly(['nullable', 'integer', Rule::exists('centers', 'id')->whereNull('deleted_at')]),
            'trigger' => $this->isUpdate ? ['prohibited'] : ['sometimes', Rule::enum(PromotionTrigger::class)],
            'scope' => [$presence, Rule::enum(PromotionScope::class)],
            'discount_type' => [$presence, Rule::enum(DiscountType::class)],
            'discount_value' => [$presence, 'integer', 'min:1'],
            'max_discount_amount' => ['nullable', 'integer', 'min:1'],
            'min_subtotal_amount' => ['nullable', 'integer', 'min:0'],
            'starts_at' => [$presence, 'date'],
            'ends_at' => ['nullable', 'date', $this->endsAfterRule()],
            'priority' => ['sometimes', 'integer', 'between:-1000,1000'],
            'stacking' => ['sometimes', Rule::enum(PromotionStacking::class)],
            'total_usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_client_usage_limit' => ['nullable', 'integer', 'min:1'],
            'first_booking_only' => ['sometimes', 'boolean'],
            'funded_by' => $adminOnly(['sometimes', Rule::enum(FundedBy::class)]),
            'center_share_bps' => $adminOnly([
                Rule::requiredIf(fn (): bool => $this->input('funded_by') === FundedBy::Shared->value),
                'nullable',
                'integer',
                'between:0,10000',
            ]),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $current = $this->currentPromotion();
            $scope = $this->input('scope', $current?->scope?->value);
            $type = $this->input('discount_type', $current?->discount_type?->value);
            $value = (int) $this->input('discount_value', $current?->discount_value ?? 0);

            if ($scope === PromotionScope::Order->value && $type === DiscountType::FixedPrice->value) {
                $validator->errors()->add('discount_type', __('validation.in', ['attribute' => 'discount type']));
            }

            if ($type === DiscountType::Percentage->value && $value > 10000) {
                $validator->errors()->add('discount_value', __('validation.max.numeric', ['attribute' => 'discount value', 'max' => 10000]));
            }
        });
    }

    /**
     * Validated attributes, with the center API's fixed ownership and funding
     * applied. A blank share only matters for shared funding.
     *
     * @return array<string, mixed>
     */
    public function promotionData(): array
    {
        $data = $this->validated();

        if ($this->isCenterContext() && ! $this->isUpdate) {
            $data['center_id'] = $this->routeCenter()->getKey();
            $data['funded_by'] = FundedBy::Center->value;
        }

        if (array_key_exists('center_share_bps', $data) && $data['center_share_bps'] === null) {
            unset($data['center_share_bps']);
        }

        return $data;
    }

    protected function endsAfterRule(): string
    {
        $start = $this->currentPromotion()?->starts_at;

        if ($this->has('starts_at') || $start === null) {
            return 'after:starts_at';
        }

        return 'after:'.$start->toDateTimeString();
    }

    protected function currentPromotion(): ?Promotion
    {
        return null;
    }
}

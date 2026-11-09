<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
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
#[BodyParam('name', 'object', 'Promotion name, keyed by locale.', required: true, example: ['en' => '15% off rides', 'ar' => 'خصم 15% على الركوب'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: true, example: '15% off rides')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: false, nullable: true, example: 'خصم 15% على الركوب')]
#[BodyParam('description', 'object', 'Description, keyed by locale.', required: false, nullable: true, example: ['en' => 'Weekend riding discount.', 'ar' => 'خصم ركوب نهاية الأسبوع.'])]
#[BodyParam('description.en', 'string', 'English description (max 2000).', required: false, nullable: true, example: 'Weekend riding discount.')]
#[BodyParam('description.ar', 'string', 'Arabic description (max 2000).', required: false, nullable: true, example: 'خصم ركوب نهاية الأسبوع.')]
#[BodyParam('badge_label', 'object', 'Short card badge, keyed by locale.', required: false, nullable: true, example: ['en' => '-15%', 'ar' => '-15%'])]
#[BodyParam('badge_label.en', 'string', 'English badge (max 60).', required: false, nullable: true, example: '-15%')]
#[BodyParam('badge_label.ar', 'string', 'Arabic badge (max 60).', required: false, nullable: true, example: '-15%')]
#[BodyParam('cover', 'string', 'UUID of a temporary upload from `POST /api/v1/media`; null removes the cover.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('campaign_id', 'integer', 'Existing campaign ID. **Admin API only**; prohibited on the center API.', required: false, nullable: true, example: 3)]
#[BodyParam('center_id', 'integer', 'Owning center (not deleted); null makes it platform-wide. **Admin API only**; on the center API the route center is used.', required: false, nullable: true, example: 8)]
#[BodyParam('trigger', 'string', '`automatic` (default) applies by itself; `coupon` requires a coupon code.', required: false, enum: PromotionTrigger::class, example: 'automatic')]
#[BodyParam('scope', 'string', '`item` discounts matching cart lines; `order` discounts the subtotal.', required: true, enum: PromotionScope::class, example: 'item')]
#[BodyParam('discount_type', 'string', '`fixed_price` is only allowed with `item` scope.', required: true, enum: DiscountType::class, example: 'percentage')]
#[BodyParam('discount_value', 'integer', 'Basis points for `percentage` (1-10000, 1250 = 12.5%), otherwise halalas (min 1).', required: true, example: 1500)]
#[BodyParam('max_discount_amount', 'integer', 'Cap in halalas (min 1); null for uncapped.', required: false, nullable: true, example: 5000)]
#[BodyParam('min_subtotal_amount', 'integer', 'Minimum cart subtotal in halalas (min 0); null for none.', required: false, nullable: true, example: 10000)]
#[BodyParam('starts_at', 'string', 'Start date-time.', required: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'End date-time; must be after `starts_at` (or the stored start on update). Null for open-ended.', required: false, nullable: true, example: '2026-08-31T23:59:59Z')]
#[BodyParam('priority', 'integer', 'Tie-break between exclusive promotions, -1000 … 1000. Default 0.', required: false, example: 0)]
#[BodyParam('stacking', 'string', 'Default `stackable`.', required: false, enum: PromotionStacking::class, example: 'stackable')]
#[BodyParam('total_usage_limit', 'integer', 'Total redemptions allowed (min 1); null for unlimited.', required: false, nullable: true, example: 1000)]
#[BodyParam('per_client_usage_limit', 'integer', 'Redemptions per client (min 1); null for unlimited.', required: false, nullable: true, example: 1)]
#[BodyParam('first_booking_only', 'boolean', 'Only for clients with no previous paid purchase. Default false.', required: false, example: false)]
#[BodyParam('funded_by', 'string', 'Who bears the discount. Default `platform`. **Admin API only**; center promotions are always center-funded.', required: false, enum: FundedBy::class, example: 'shared')]
#[BodyParam('center_share_bps', 'integer', 'Center share in basis points (0-10000). Required when `funded_by` is `shared`. **Admin API only**.', required: false, nullable: true, example: 5000)]
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

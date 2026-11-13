<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Services\Enums\ServiceType;

/**
 * Body shared by create and update. Update replaces the whole definition:
 * options sent with an `id` are updated in place, options without one are
 * created and the others (an omitted `daily_price` or `extra_options`
 * included) are deleted. Only the media fields are left untouched when omitted.
 */
#[BodyParam('name', 'object', 'Name keyed by locale.', example: ['ar' => 'إسطبل شهري', 'en' => 'Monthly stabling'])]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', example: 'إسطبل شهري')]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, nullable: true, example: 'Monthly stabling')]
#[BodyParam('description', 'object', 'Description keyed by locale.', example: ['ar' => 'إيواء الخيل مع العلف والتنظيف.', 'en' => 'Horse boarding with feed and cleaning.'])]
#[BodyParam('description.ar', 'string', 'Arabic description.', example: 'إيواء الخيل مع العلف والتنظيف.')]
#[BodyParam('description.en', 'string', 'English description.', required: false, nullable: true, example: 'Horse boarding with feed and cleaning.')]
#[BodyParam('monthly_prices', 'object[]', 'Monthly prices (1-12), each for a distinct number of months.', example: [['months' => 1, 'price' => 150000], ['months' => 3, 'price' => 420000]])]
#[BodyParam('monthly_prices[].id', 'integer', 'ID of an existing monthly price to update in place; omit to create one.', required: false, example: 70)]
#[BodyParam('monthly_prices[].months', 'integer', 'Number of months (1-24), distinct within the list.', example: 1)]
#[BodyParam('monthly_prices[].price', 'integer', 'Price in halalas (min 0).', example: 150000)]
#[BodyParam('daily_price', 'object', 'Optional price per day; null or omitted removes it.', required: false, nullable: true, example: ['price' => 6000])]
#[BodyParam('daily_price.id', 'integer', 'ID of the existing daily price to update in place.', required: false, example: 71)]
#[BodyParam('daily_price.price', 'integer', 'Price per day in halalas (min 0).', required: false, example: 6000)]
#[BodyParam('extra_options', 'object[]', 'Optional named extras (max 20); omitted removes them all.', required: false, example: [['name' => 'Daily grooming', 'price' => 30000]])]
#[BodyParam('extra_options[].id', 'integer', 'ID of an existing extra to update in place; omit to create one.', required: false, example: 72)]
#[BodyParam('extra_options[].name', 'string', 'Extra name (max 255).', example: 'Daily grooming')]
#[BodyParam('extra_options[].price', 'integer', 'Price in halalas (min 0).', example: 30000)]
#[BodyParam('cover', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or the current cover; null removes it; omit to keep it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('images', 'string[]', 'Full gallery in display order (max 10 distinct UUIDs): existing ones are kept, new temporary uploads attached, omitted ones removed. Omit to keep the gallery.', required: false, example: ['2d3e4f5a-6b7c-4d8e-9f0a-1b2c3d4e5f6a'])]
class StablingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', [$this->route('center'), ServiceType::Stabling]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'array'],
            'description.ar' => ['required', 'string'],
            'description.en' => ['nullable', 'string'],
            'monthly_prices' => ['required', 'array', 'min:1', 'max:12'],
            'monthly_prices.*.id' => ['sometimes', 'integer', 'distinct'],
            'monthly_prices.*.months' => ['required', 'integer', 'min:1', 'max:24', 'distinct'],
            'monthly_prices.*.price' => ['required', 'integer', 'min:0'],
            'daily_price' => ['sometimes', 'nullable', 'array'],
            'daily_price.id' => ['sometimes', 'integer'],
            'daily_price.price' => ['required_with:daily_price', 'integer', 'min:0'],
            'extra_options' => ['sometimes', 'array', 'max:20'],
            'extra_options.*.id' => ['sometimes', 'integer', 'distinct'],
            'extra_options.*.name' => ['required', 'string', 'max:255'],
            'extra_options.*.price' => ['required', 'integer', 'min:0'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}

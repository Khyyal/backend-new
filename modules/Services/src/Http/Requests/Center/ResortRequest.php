<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, description, days and price_options); only the media fields are
 * optional and are left untouched when omitted.
 */
#[BodyParam('name', 'object', 'Name keyed by locale.', example: ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride'])]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', example: 'ركوب للمبتدئين')]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, nullable: true, example: 'Beginner ride')]
#[BodyParam('description', 'object', 'Description keyed by locale.', example: ['ar' => 'جولة بإشراف مدرب للمبتدئين.', 'en' => 'A guided ride for first-timers.'])]
#[BodyParam('description.ar', 'string', 'Arabic description.', example: 'جولة بإشراف مدرب للمبتدئين.')]
#[BodyParam('description.en', 'string', 'English description.', required: false, nullable: true, example: 'A guided ride for first-timers.')]
#[BodyParam('days', 'object[]', 'Day prices (1-7); days not listed cannot be booked.', example: [['day' => 5, 'price' => 120000]])]
#[BodyParam('days[].day', 'integer', 'Distinct weekday, 0 = Sunday … 6 = Saturday.', example: 5)]
#[BodyParam('days[].price', 'integer', 'Day price in halalas (min 0).', example: 120000)]
#[BodyParam('price_options', 'object[]', 'Full list of extras (1-20); replaces the current ones.', example: [['name' => 'Adult ticket', 'price' => 5000]])]
#[BodyParam('price_options[].name', 'string', 'Option name (max 255).', example: 'Adult ticket')]
#[BodyParam('price_options[].price', 'integer', 'Price in halalas (min 0).', example: 5000)]
#[BodyParam('cover', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or the current cover; null removes it; omit to keep it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('images', 'string[]', 'Full gallery in display order (max 10 distinct UUIDs): existing ones are kept, new temporary uploads attached, omitted ones removed. Omit to keep the gallery.', required: false, example: ['2d3e4f5a-6b7c-4d8e-9f0a-1b2c3d4e5f6a'])]
#[BodyParam('settings', 'object', 'Booleans keyed by `enable_cash_on_arrival`, `close_all_training_bookings`, `close_all_visit_bookings`, `close_all_recreational_ride_bookings`, `close_all_resort_bookings`; other keys are rejected. On update replaces all settings (omitted keys become false); omit to leave them untouched.', required: false, example: ['enable_cash_on_arrival' => true, 'close_all_training_bookings' => false, 'close_all_visit_bookings' => false, 'close_all_recreational_ride_bookings' => false, 'close_all_resort_bookings' => false])]
class ResortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', [$this->route('center'), ServiceType::Resort]);
    }

    /**
     * `days` is a per-weekday base price list (partial coverage allowed, no
     * duplicate weekdays): `[{ day, price }]`, `day` is `0`-`6`. `price_options`
     * is a separate list of named price tiers: `[{ name, price }]`. `cover`
     * and `images` take media ids from the Support media API; `images` is the
     * full desired list in display order.
     *
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
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*.day' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.price' => ['required', 'integer', 'min:0'],
            'price_options' => ['required', 'array', 'min:1', 'max:20'],
            'price_options.*.name' => ['required', 'string', 'max:255'],
            'price_options.*.price' => ['required', 'integer', 'min:0'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
            ...Resort::settingsRules(),
        ];
    }
}

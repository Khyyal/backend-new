<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, description, days and price_options); only the media fields are
 * optional and are left untouched when omitted.
 */
class ResortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('center_user'))->allows('manageServices', $this->route('center'));
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
            'days.*.price' => ['required', 'numeric', 'min:0'],
            'price_options' => ['required', 'array', 'min:1', 'max:20'],
            'price_options.*.name' => ['required', 'string', 'max:255'],
            'price_options.*.price' => ['required', 'numeric', 'min:0'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}

<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('name', 'object', 'City name; both locales are required.', required: false, example: ['en' => 'Riyadh', 'ar' => 'الرياض'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, example: 'Riyadh')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: false, example: 'الرياض')]
#[BodyParam('lat', 'number', 'Latitude, -90 to 90.', required: false, example: 24.7136)]
#[BodyParam('lng', 'number', 'Longitude, -180 to 180.', required: false, example: 46.6753)]
#[BodyParam('radius', 'integer', 'Coverage radius in km, 1-1000.', required: false, example: 40)]
class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('city'));
    }

    /**
     * Partial update: only the fields sent change.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'array:en,ar'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'lat' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}

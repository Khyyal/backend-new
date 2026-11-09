<?php

namespace Modules\Support\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Support\Models\City;

#[BodyParam('name', 'object', 'City name; both locales are required.', required: true, example: ['en' => 'Riyadh', 'ar' => 'الرياض'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: true, example: 'Riyadh')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: true, example: 'الرياض')]
#[BodyParam('lat', 'number', 'Latitude, -90 to 90.', required: true, example: 24.7136)]
#[BodyParam('lng', 'number', 'Longitude, -180 to 180.', required: true, example: 46.6753)]
#[BodyParam('radius', 'integer', 'Coverage radius in km, 1-1000.', required: true, example: 40)]
class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', City::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array:en,ar'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}

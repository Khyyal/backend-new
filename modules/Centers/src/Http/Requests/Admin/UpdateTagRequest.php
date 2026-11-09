<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('name', 'object', 'Tag name; both locales are required when sent.', required: false, example: ['en' => 'Family friendly', 'ar' => 'مناسب للعائلات'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, example: 'Family friendly')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: false, example: 'مناسب للعائلات')]
class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('tag'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'array:en,ar'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
        ];
    }
}

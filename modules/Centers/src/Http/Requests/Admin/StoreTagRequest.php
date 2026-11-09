<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Models\Tag;

#[BodyParam('name', 'object', 'Tag name; both locales are required.', required: true, example: ['en' => 'Family friendly', 'ar' => 'مناسب للعائلات'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: true, example: 'Family friendly')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: true, example: 'مناسب للعائلات')]
class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', Tag::class);
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
        ];
    }
}

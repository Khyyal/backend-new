<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Services\Enums\ServiceType;

#[BodyParam('terms', 'object', 'Terms keyed by locale.', example: ['ar' => 'يجب الحضور قبل 15 دقيقة.', 'en' => 'Arrive 15 minutes early.'])]
#[BodyParam('terms.ar', 'string', 'Arabic terms.', example: 'يجب الحضور قبل 15 دقيقة.')]
#[BodyParam('terms.en', 'string', 'English terms.', required: false, nullable: true, example: 'Arrive 15 minutes early.')]
class ServiceTypeTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('type');

        return Gate::forUser($this->user())->allows('manageServices', [
            $this->route('center'),
            $type instanceof ServiceType ? $type : ServiceType::from($type),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'terms' => ['required', 'array'],
            'terms.ar' => ['required', 'string'],
            'terms.en' => ['nullable', 'string'],
        ];
    }
}

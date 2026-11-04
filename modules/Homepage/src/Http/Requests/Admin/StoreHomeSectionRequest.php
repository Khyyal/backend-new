<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\HomeSection;

class StoreHomeSectionRequest extends FormRequest
{
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
        $section = $this->route('section');
        $start = $section instanceof HomeSection ? $section->starts_at : null;

        return [
            'key' => [$this->isUpdate ? 'sometimes' : 'required', 'string', 'max:100', 'alpha_dash', Rule::unique('home_sections', 'key')->ignore($section instanceof HomeSection ? $section->getKey() : null)],
            // The type decides what fills the section and how `config` is read, so it is fixed at creation.
            'type' => $this->isUpdate ? ['prohibited'] : ['required', Rule::enum(HomeSectionType::class)],
            'title' => ['sometimes', 'nullable', 'array'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.ar' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'array'],
            'subtitle.en' => ['nullable', 'string', 'max:500'],
            'subtitle.ar' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'between:-100000,100000'],
            'status' => ['sometimes', Rule::enum(PublishStatus::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', $this->has('starts_at') || $start === null ? 'after:starts_at' : 'after:'.$start->toDateTimeString()],
            ...$this->configRules(),
        ];
    }

    /**
     * Every type reads `limit`; `radius_km` only applies to sections showing centers or their content.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function configRules(): array
    {
        $section = $this->route('section');
        $type = $section instanceof HomeSection ? $section->type : HomeSectionType::tryFrom((string) $this->input('type'));

        $allowed = $type?->usesRadius() ? 'limit,radius_km' : 'limit';

        return [
            'config' => ['sometimes', 'nullable', 'array:'.$allowed],
            'config.limit' => ['sometimes', 'integer', 'between:1,50'],
            'config.radius_km' => ['sometimes', 'nullable', 'numeric', 'between:0.1,1000'],
        ];
    }
}

<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\Banner;
use Modules\Homepage\Models\HomeSection;

class StoreBannerRequest extends FormRequest
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
        $presence = $this->isUpdate ? 'sometimes' : 'required';
        $banner = $this->route('banner');
        $start = $banner instanceof Banner ? $banner->starts_at : null;

        return [
            'title' => [$presence, 'array'],
            'title.en' => [$presence, 'string', 'max:255'],
            'title.ar' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'array'],
            'subtitle.en' => ['nullable', 'string', 'max:500'],
            'subtitle.ar' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['sometimes', 'nullable', 'array'],
            'cta_label.en' => ['nullable', 'string', 'max:60'],
            'cta_label.ar' => ['nullable', 'string', 'max:60'],
            'home_section_id' => ['sometimes', 'nullable', 'integer', 'exists:home_sections,id'],
            'action_type' => ['sometimes', Rule::enum(BannerActionType::class)],
            'action_target_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'action_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'center_id' => ['sometimes', 'nullable', 'integer', Rule::exists('centers', 'id')->whereNull('deleted_at')],
            'sort_order' => ['sometimes', 'integer', 'between:-100000,100000'],
            'status' => ['sometimes', Rule::enum(PublishStatus::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', $this->has('starts_at') || $start === null ? 'after:starts_at' : 'after:'.$start->toDateTimeString()],
            'images' => ['sometimes', 'array:'.implode(',', Banner::LOCALES)],
            'images.*' => ['nullable', 'uuid'],
        ];
    }

    /**
     * The action needs its target (or URL), checked against the stored banner on partial updates.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $banner = $this->route('banner');
            $current = $banner instanceof Banner ? $banner : null;
            $type = BannerActionType::from($this->input('action_type', $current?->action_type->value ?? BannerActionType::None->value));
            $targetId = $this->has('action_target_id') ? $this->input('action_target_id') : $current?->action_target_id;
            $url = $this->has('action_url') ? $this->input('action_url') : $current?->action_url;

            if ($type === BannerActionType::Url && empty($url)) {
                $validator->errors()->add('action_url', __('validation.required', ['attribute' => 'action url']));
            } elseif ($type->targetTable() !== null) {
                if (empty($targetId)) {
                    $validator->errors()->add('action_target_id', __('validation.required', ['attribute' => 'action target']));
                } elseif (! DB::table($type->targetTable())->where('id', $targetId)->whereNull('deleted_at')->exists()) {
                    $validator->errors()->add('action_target_id', __('validation.exists', ['attribute' => 'action target']));
                }
            }

            $sectionId = $this->input('home_section_id');

            if ($sectionId !== null && HomeSection::query()->whereKey($sectionId)->where('type', '!=', HomeSectionType::BannerCarousel->value)->exists()) {
                $validator->errors()->add('home_section_id', __('homepage::messages.not_a_banner_section'));
            }
        });
    }
}

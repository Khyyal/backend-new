<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Homepage\Enums\BannerActionType;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\Banner;
use Modules\Homepage\Models\HomeSection;

#[BodyParam('title', 'object', 'Title keyed by locale.', required: true, example: ['en' => 'Summer rides', 'ar' => 'ركوب الصيف'])]
#[BodyParam('title.en', 'string', 'English title (max 255).', required: true, example: 'Summer rides')]
#[BodyParam('title.ar', 'string', 'Arabic title (max 255).', required: false, nullable: true, example: 'ركوب الصيف')]
#[BodyParam('subtitle', 'object', 'Subtitle keyed by locale.', required: false, nullable: true, example: ['en' => 'Up to 20% off', 'ar' => 'خصم حتى 20%'])]
#[BodyParam('subtitle.en', 'string', 'English (max 500).', required: false, nullable: true, example: 'Up to 20% off')]
#[BodyParam('subtitle.ar', 'string', 'Arabic (max 500).', required: false, nullable: true, example: 'خصم حتى 20%')]
#[BodyParam('cta_label', 'object', 'Button label keyed by locale.', required: false, nullable: true, example: ['en' => 'Book now', 'ar' => 'احجز الآن'])]
#[BodyParam('cta_label.en', 'string', 'English (max 60).', required: false, nullable: true, example: 'Book now')]
#[BodyParam('cta_label.ar', 'string', 'Arabic (max 60).', required: false, nullable: true, example: 'احجز الآن')]
#[BodyParam('home_section_id', 'integer', 'A `banner_carousel` section; null to detach.', required: false, nullable: true, example: 2)]
#[BodyParam('action_type', 'string', '`none`, `url` (needs `action_url`), or `center`/`service`/`promotion`/`campaign` (need `action_target_id`).', required: false, enum: BannerActionType::class, example: 'promotion')]
#[BodyParam('action_target_id', 'integer', 'Target ID for record actions (min 1).', required: false, nullable: true, example: 12)]
#[BodyParam('action_url', 'string', 'http(s) URL for the `url` action (max 2048).', required: false, nullable: true, example: 'https://khyyal.com/summer')]
#[BodyParam('center_id', 'integer', 'Tie the banner to a center (not deleted): hidden when the center is hidden or outside the section radius.', required: false, nullable: true, example: 8)]
#[BodyParam('sort_order', 'integer', 'Order, -100000 … 100000 (ascending).', required: false, example: 0)]
#[BodyParam('status', 'string', 'Publish status. Default `draft`.', required: false, enum: PublishStatus::class, example: 'published')]
#[BodyParam('starts_at', 'string', 'Schedule start (date-time); null for no start.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'Schedule end, after `starts_at`; null for open-ended.', required: false, nullable: true, example: '2026-08-31T23:59:59Z')]
#[BodyParam('images', 'object', 'One temporary upload UUID per locale (`en`, `ar`). A locale without an image falls back to the other.', required: false, example: ['en' => '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90', 'ar' => '1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f'])]
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

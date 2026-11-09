<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\PublishStatus;
use Modules\Homepage\Models\HomeSection;

#[BodyParam('key', 'string', 'Unique key: letters, numbers, dashes, underscores (max 100).', required: true, example: 'summer-deals')]
#[BodyParam('type', 'string', 'Section type, fixed after creation: `banner_carousel` shows attached banners; `curated_*` show picked items; `top_centers` fills itself (nearest first with a client position, else most popular).', enum: HomeSectionType::class, example: 'curated_promotions')]
#[BodyParam('title', 'object', 'Title keyed by locale.', required: false, nullable: true, example: ['en' => 'Summer deals', 'ar' => 'عروض الصيف'])]
#[BodyParam('title.en', 'string', 'English (max 255).', required: false, nullable: true, example: 'Summer deals')]
#[BodyParam('title.ar', 'string', 'Arabic (max 255).', required: false, nullable: true, example: 'عروض الصيف')]
#[BodyParam('subtitle', 'object', 'Subtitle keyed by locale.', required: false, nullable: true, example: ['en' => 'Limited time', 'ar' => 'لفترة محدودة'])]
#[BodyParam('subtitle.en', 'string', 'English (max 500).', required: false, nullable: true, example: 'Limited time')]
#[BodyParam('subtitle.ar', 'string', 'Arabic (max 500).', required: false, nullable: true, example: 'لفترة محدودة')]
#[BodyParam('sort_order', 'integer', 'Order, -100000 … 100000 (ascending).', required: false, example: 0)]
#[BodyParam('status', 'string', 'Publish status. Default `draft`.', required: false, enum: PublishStatus::class, example: 'published')]
#[BodyParam('starts_at', 'string', 'Schedule start (date-time); null for no start.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'Schedule end, after `starts_at`; null for open-ended.', required: false, nullable: true, example: '2026-08-31T23:59:59Z')]
#[BodyParam('config', 'object', 'Options. Only `limit`, plus `radius_km` for center, service, banner and top-centers sections.', required: false, nullable: true, example: ['limit' => 10, 'radius_km' => 50])]
#[BodyParam('config.limit', 'integer', 'Items shown on the home page, 1-50. Default 10.', required: false, example: 10)]
#[BodyParam('config.radius_km', 'number', 'Hide content farther than this from the client (0.1-1000 km).', required: false, nullable: true, example: 50)]
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

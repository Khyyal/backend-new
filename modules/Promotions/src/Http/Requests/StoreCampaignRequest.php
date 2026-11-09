<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Promotions\Enums\CampaignStatus;
use Modules\Promotions\Models\Campaign;

#[BodyParam('name', 'object', 'Campaign name, keyed by locale.', required: true, example: ['en' => 'Summer 2026', 'ar' => 'صيف 2026'])]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: true, example: 'Summer 2026')]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', required: false, nullable: true, example: 'صيف 2026')]
#[BodyParam('description', 'object', 'Description, keyed by locale.', required: false, nullable: true, example: ['en' => 'Summer deals across all centers.', 'ar' => 'عروض الصيف في جميع المراكز.'])]
#[BodyParam('description.en', 'string', 'English description (max 2000).', required: false, nullable: true, example: 'Summer deals across all centers.')]
#[BodyParam('description.ar', 'string', 'Arabic description (max 2000).', required: false, nullable: true, example: 'عروض الصيف في جميع المراكز.')]
#[BodyParam('cover', 'string', 'UUID of a temporary upload from `POST /api/v1/media` to use as cover; null removes it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('slug', 'string', 'Unique slug (letters, numbers, dashes, underscores; max 255).', required: true, example: 'summer-2026')]
#[BodyParam('starts_at', 'string', 'Start date-time; null for no start bound.', required: false, nullable: true, example: '2026-06-01T00:00:00Z')]
#[BodyParam('ends_at', 'string', 'End date-time; must be after `starts_at`.', required: false, nullable: true, example: '2026-08-31T23:59:59Z')]
#[BodyParam('status', 'string', 'Campaign status. Default `draft`.', required: false, enum: CampaignStatus::class, example: 'draft')]
class StoreCampaignRequest extends FormRequest
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
        $campaign = $this->route('campaign');

        return [
            'name' => [$presence, 'array'],
            'name.en' => [$presence, 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string', 'max:2000'],
            'description.ar' => ['nullable', 'string', 'max:2000'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'slug' => [$presence, 'string', 'max:255', 'alpha_dash', Rule::unique('campaigns', 'slug')->ignore($campaign instanceof Campaign ? $campaign->getKey() : null)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', Rule::enum(CampaignStatus::class)],
        ];
    }
}

<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Promotions\Enums\CampaignStatus;
use Modules\Promotions\Models\Campaign;

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

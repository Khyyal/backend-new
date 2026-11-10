<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\TrainingService;

/**
 * Body shared by create and update. Update replaces the whole definition
 * (name, description and price_options); `trainer_ids` and the media fields
 * are optional and are left untouched when omitted.
 */
#[BodyParam('name', 'object', 'Name keyed by locale.', example: ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride'])]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', example: 'ركوب للمبتدئين')]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, nullable: true, example: 'Beginner ride')]
#[BodyParam('description', 'object', 'Description keyed by locale.', example: ['ar' => 'جولة بإشراف مدرب للمبتدئين.', 'en' => 'A guided ride for first-timers.'])]
#[BodyParam('description.ar', 'string', 'Arabic description.', example: 'جولة بإشراف مدرب للمبتدئين.')]
#[BodyParam('description.en', 'string', 'English description.', required: false, nullable: true, example: 'A guided ride for first-timers.')]
#[BodyParam('price_options', 'object[]', 'Full list of price options by duration (1-20).', example: [['duration' => 60, 'price' => 15000, 'id' => 70]])]
#[BodyParam('price_options[].duration', 'integer', 'Session length in minutes (min 1).', example: 60)]
#[BodyParam('price_options[].price', 'integer', 'Price in halalas (min 0).', example: 15000)]
#[BodyParam('price_options[].id', 'integer', 'Update only: keep this existing option (and its package) in place. Existing options not sent are deleted with their package.', required: false, example: 70)]
#[BodyParam('packages', 'object[]', 'Session packages, at most one per option (max 20). On update replaces all packages; omit to leave them untouched.', required: false, example: [['price_option' => 0, 'count' => 8, 'price' => 100000, 'duration' => 3]])]
#[BodyParam('packages[].price_option', 'integer', '0-based index into `price_options` (distinct).', example: 0)]
#[BodyParam('packages[].count', 'integer', 'Sessions in the package (min 1).', example: 8)]
#[BodyParam('packages[].price', 'integer', 'Package price in halalas (min 0).', example: 100000)]
#[BodyParam('packages[].duration', 'integer', 'Validity in months (1-120).', example: 3)]
#[BodyParam('packages[].status', 'string', 'Inactive packages stay listed for the center but are hidden from clients and cannot be bought. Omitted: an existing package keeps its status, a new one is active.', required: false, enum: ['active', 'inactive'], example: 'active')]
#[BodyParam('trainer_ids', 'integer[]', 'Full list of distinct trainer IDs of this center; omit to leave untouched.', required: false, example: [6, 7])]
#[BodyParam('cover', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or the current cover; null removes it; omit to keep it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('images', 'string[]', 'Full gallery in display order (max 10 distinct UUIDs): existing ones are kept, new temporary uploads attached, omitted ones removed. Omit to keep the gallery.', required: false, example: ['2d3e4f5a-6b7c-4d8e-9f0a-1b2c3d4e5f6a'])]
#[BodyParam('settings', 'object', 'Booleans keyed by `enable_cash_on_arrival`, `enable_single_session_booking`, `enable_package_booking`; other keys are rejected. On update replaces all settings (omitted keys become false); omit to leave them untouched.', required: false, example: ['enable_cash_on_arrival' => true, 'enable_single_session_booking' => false, 'enable_package_booking' => false])]
class TrainingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', [$this->route('center'), ServiceType::Training]);
    }

    /**
     * `trainer_ids` must be trainers of the same center. `cover` and `images`
     * take media ids from the Support media API; `images` is the full desired
     * list in display order.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center|null $center */
        $center = $this->route('center');

        return [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'array'],
            'description.ar' => ['required', 'string'],
            'description.en' => ['nullable', 'string'],
            'price_options' => ['required', 'array', 'min:1', 'max:20'],
            'price_options.*.duration' => ['required', 'integer', 'min:1'],
            'price_options.*.price' => ['required', 'integer', 'min:0'],
            'trainer_ids' => ['sometimes', 'array'],
            'trainer_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('trainers', 'id')
                    ->where('center_id', $center?->getKey())
                    ->whereNull('deleted_at'),
            ],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
            ...TrainingService::settingsRules(),
            ...TrainingService::packageRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => TrainingService::validatePackageIndexes($validator, $this->all()),
        ];
    }
}

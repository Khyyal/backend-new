<?php

namespace Modules\Services\Http\Requests\Center;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\ServicePackage;
use Modules\Services\Models\TrainingService;

/**
 * Body shared by create and update. Update replaces the whole definition,
 * packages included; the media fields are optional and left untouched when omitted.
 */
#[BodyParam('name', 'object', 'Name keyed by locale.', example: ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride'])]
#[BodyParam('name.ar', 'string', 'Arabic name (max 255).', example: 'ركوب للمبتدئين')]
#[BodyParam('name.en', 'string', 'English name (max 255).', required: false, nullable: true, example: 'Beginner ride')]
#[BodyParam('description', 'object', 'Description keyed by locale.', example: ['ar' => 'جولة بإشراف مدرب للمبتدئين.', 'en' => 'A guided ride for first-timers.'])]
#[BodyParam('description.ar', 'string', 'Arabic description.', example: 'جولة بإشراف مدرب للمبتدئين.')]
#[BodyParam('description.en', 'string', 'English description.', required: false, nullable: true, example: 'A guided ride for first-timers.')]
#[BodyParam('price', 'integer', 'Membership price in halalas (min 0).', example: 250000)]
#[BodyParam('duration', 'integer', 'Validity in months (1-120).', example: 6)]
#[BodyParam('color', 'string', 'Card color `#RRGGBB`.', example: '#8B4513')]
#[BodyParam('visit_service_id', 'integer', 'A visit service of this center with free entries for members.', required: false, nullable: true, example: 33)]
#[BodyParam('max_visits_per_day', 'integer', 'Free visit entries per day (min 1); required with `visit_service_id`.', required: false, nullable: true, example: 1)]
#[BodyParam('packages', 'object[]', 'Full list of included service packages (1-20) of this center\'s services. Memberships already sold keep their packages.', example: [['service_package_id' => 5, 'trainer_id' => 6]])]
#[BodyParam('packages[].service_package_id', 'integer', 'Distinct, non-deleted service package ID (see `price_options[].package.id`).', example: 5)]
#[BodyParam('packages[].trainer_id', 'integer', 'Only for a training package: a trainer of that training service.', required: false, nullable: true, example: 6)]
#[BodyParam('cover', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or the current cover; null removes it; omit to keep it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('images', 'string[]', 'Full gallery in display order (max 10 distinct UUIDs): existing ones are kept, new temporary uploads attached, omitted ones removed. Omit to keep the gallery.', required: false, example: ['2d3e4f5a-6b7c-4d8e-9f0a-1b2c3d4e5f6a'])]
class MembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageServices', [$this->route('center'), ServiceType::Membership]);
    }

    /**
     * `visit_service_id` must be a visit service of the same center.
     * `packages.*.service_package_id` are service packages of the same center;
     * `packages.*.trainer_id` binds a training package to one of its trainers.
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
            'price' => ['required', 'integer', 'min:0'],
            'duration' => ['required', 'integer', 'min:1', 'max:120'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'visit_service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')
                    ->where('center_id', $center?->getKey())
                    ->where('type', ServiceType::Visit->value)
                    ->whereNull('deleted_at'),
            ],
            'max_visits_per_day' => ['nullable', 'required_with:visit_service_id', 'integer', 'min:1'],
            'packages' => ['required', 'array', 'min:1', 'max:20'],
            'packages.*.service_package_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('service_packages', 'id')->whereNull('deleted_at'),
            ],
            'packages.*.trainer_id' => ['nullable', 'integer'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validatePackages($validator),
        ];
    }

    /**
     * Each package belongs to a service of this center, and a trainer is only
     * accepted on a training package whose service has that trainer.
     */
    private function validatePackages(Validator $validator): void
    {
        $packages = collect((array) $this->input('packages', []))->filter(fn ($package) => is_array($package));
        $ids = $packages->pluck('service_package_id')->filter(fn ($id) => is_int($id))->all();

        if ($ids === []) {
            return;
        }

        $servicePackages = ServicePackage::query()
            ->whereKey($ids)
            ->with('service.serviceable')
            ->get()
            ->keyBy('id');

        foreach ($packages as $index => $package) {
            $servicePackage = $servicePackages->get($package['service_package_id'] ?? null);

            if ($servicePackage === null || $validator->errors()->has("packages.{$index}.service_package_id")) {
                continue;
            }

            if ($servicePackage->service?->center_id !== $this->route('center')?->getKey()) {
                $validator->errors()->add("packages.{$index}.service_package_id", 'The package does not belong to this center.');

                continue;
            }

            $trainerId = $package['trainer_id'] ?? null;

            if ($trainerId === null || $validator->errors()->has("packages.{$index}.trainer_id")) {
                continue;
            }

            $serviceable = $servicePackage->service->serviceable;

            if (! $serviceable instanceof TrainingService) {
                $validator->errors()->add("packages.{$index}.trainer_id", 'A trainer can only be set on a training package.');

                continue;
            }

            if (! $serviceable->trainers()->whereKey($trainerId)->exists()) {
                $validator->errors()->add("packages.{$index}.trainer_id", 'The trainer is not assigned to this training service.');
            }
        }
    }
}

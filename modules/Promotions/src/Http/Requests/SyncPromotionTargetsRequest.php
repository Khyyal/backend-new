<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Promotions\Enums\TargetMode;
use Modules\Promotions\Enums\TargetType;
use Modules\Promotions\Http\Requests\Concerns\ResolvesPromotionContext;

/**
 * On the center API only the center's own services can be targeted.
 */
#[BodyParam('targets', 'object[]', 'Full replacement list of targets (max 500). Send `[]` to target everything the promotion can reach.', example: [['type' => 'service', 'id' => 31, 'mode' => 'include']])]
#[BodyParam('targets[].type', 'string', 'Target kind. The center API accepts only `service` (its own services).', enum: TargetType::class, example: 'service')]
#[BodyParam('targets[].id', 'integer', 'ID of an existing, non-deleted center or service. Each type+id pair must be unique.', example: 31)]
#[BodyParam('targets[].mode', 'string', 'Default `include`.', required: false, nullable: true, enum: TargetMode::class, example: 'include')]
class SyncPromotionTargetsRequest extends FormRequest
{
    use ResolvesPromotionContext;

    private const TABLES = [
        'center' => 'centers',
        'service' => 'services',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $types = $this->isCenterContext() ? [TargetType::Service] : TargetType::cases();

        return [
            'targets' => ['present', 'array', 'max:500'],
            'targets.*.type' => ['required', Rule::in(array_map(fn (TargetType $type): string => $type->value, $types))],
            'targets.*.id' => ['required', 'integer', 'min:1'],
            'targets.*.mode' => ['nullable', Rule::enum(TargetMode::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $seen = [];

            foreach ($this->input('targets', []) as $index => $target) {
                $key = $target['type'].':'.$target['id'];

                if (isset($seen[$key])) {
                    $validator->errors()->add("targets.{$index}.id", __('validation.distinct', ['attribute' => 'target']));

                    continue;
                }

                $seen[$key] = true;

                $exists = DB::table(self::TABLES[$target['type']])
                    ->where('id', $target['id'])
                    ->whereNull('deleted_at')
                    ->when($this->isCenterContext(), fn ($query) => $query->where('center_id', $this->routeCenter()->getKey()))
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add("targets.{$index}.id", __('validation.exists', ['attribute' => 'target']));
                }
            }
        });
    }
}

<?php

namespace Modules\Promotions\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Promotions\Enums\TargetMode;
use Modules\Promotions\Enums\TargetType;
use Modules\Promotions\Http\Requests\Concerns\ResolvesPromotionContext;

/**
 * On the center API only the center's own services can be targeted.
 */
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

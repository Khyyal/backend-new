<?php

namespace Modules\Homepage\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Homepage\Enums\ItemableType;
use Modules\Homepage\Models\HomeSection;

class SyncHomeSectionItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:200'],
            'items.*.type' => ['required', Rule::enum(ItemableType::class)],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.starts_at' => ['nullable', 'date'],
            'items.*.ends_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Items must match the section's item type, appear once and point at an existing record.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $section = $this->route('section');

            if ($validator->errors()->isNotEmpty() || ! $section instanceof HomeSection) {
                return;
            }

            $expected = $section->type->itemType();

            if ($expected === null) {
                $validator->errors()->add('items', __('homepage::messages.items_not_supported'));

                return;
            }

            $items = collect($this->input('items', []));
            $existing = $expected->modelClass()::query()
                ->whereKey($items->pluck('id')->map(fn ($id): int => (int) $id)->all())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $seen = [];

            foreach ($items as $index => $item) {
                if ($item['type'] !== $expected->value) {
                    $validator->errors()->add("items.{$index}.type", __('homepage::messages.item_type_mismatch', ['type' => $expected->value]));

                    continue;
                }

                if (isset($seen[(int) $item['id']])) {
                    $validator->errors()->add("items.{$index}.id", __('validation.distinct', ['attribute' => 'item']));

                    continue;
                }

                $seen[(int) $item['id']] = true;

                if (! in_array((int) $item['id'], $existing, true)) {
                    $validator->errors()->add("items.{$index}.id", __('validation.exists', ['attribute' => 'item']));
                }

                if (! empty($item['starts_at']) && ! empty($item['ends_at']) && strtotime($item['ends_at']) <= strtotime($item['starts_at'])) {
                    $validator->errors()->add("items.{$index}.ends_at", __('validation.after', ['attribute' => 'ends at', 'date' => 'starts at']));
                }
            }
        });
    }
}

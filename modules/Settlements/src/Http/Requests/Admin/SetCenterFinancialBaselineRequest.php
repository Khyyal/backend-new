<?php

namespace Modules\Settlements\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Settlements\Models\CenterFinancialBaseline;
use Modules\Settlements\Models\CenterPayout;

#[BodyParam('cutoff_at', 'string', 'Moment the figures are true as of (ISO-8601, not in the future). Required for the first confirmation; afterwards it is fixed and may only be repeated unchanged.', required: false, example: '2026-11-10T21:00:00+00:00')]
#[BodyParam('target_balance', 'integer', 'What the platform owed the center and had not paid at the cutoff, in halalas. Negative when the center owed the platform.', example: 812500)]
#[BodyParam('target_platform_fees', 'integer', 'True total platform fees up to the cutoff, in halalas. Null leaves the migrated figure as is.', required: false, nullable: true, example: 1520000)]
#[BodyParam('target_paid_out', 'integer', 'Total transferred to the center before the migration, in halalas.', required: false, nullable: true, example: 4300000)]
#[BodyParam('target_gross_sales', 'integer', 'True total of counted sales up to the cutoff, in halalas. Null leaves the migrated figure as is.', required: false, nullable: true, example: 15200000)]
#[BodyParam('target_refunds', 'integer', 'True total refunded on online payments up to the cutoff, in halalas. Null leaves the migrated figure as is.', required: false, nullable: true, example: 240000)]
#[BodyParam('note', 'string', 'Where the figures come from (max 500).', example: 'Finance reconciliation sheet, November 2026.')]
class SetCenterFinancialBaselineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageBaseline', CenterPayout::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cutoff_at' => [Rule::requiredIf(fn (): bool => $this->confirmedBaseline() === null), 'nullable', 'date', 'before_or_equal:now'],
            'target_balance' => ['required', 'integer'],
            'target_platform_fees' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'target_paid_out' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'target_gross_sales' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'target_refunds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $baseline = $this->confirmedBaseline();
                $cutoff = $this->input('cutoff_at');

                if ($baseline === null || blank($cutoff) || $validator->errors()->has('cutoff_at')) {
                    return;
                }

                if (! Carbon::parse($cutoff)->equalTo($baseline->cutoff_at)) {
                    $validator->errors()->add('cutoff_at', 'The cutoff of a confirmed baseline cannot be changed.');
                }
            },
        ];
    }

    private function confirmedBaseline(): ?CenterFinancialBaseline
    {
        return CenterFinancialBaseline::query()
            ->where('center_id', $this->route('center')->getKey())
            ->whereNotNull('confirmed_at')
            ->first();
    }
}

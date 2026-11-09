<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Billing\Enums\PlanStatus;

#[BodyParam('status', 'string', 'New status; `in_active` hides the plan from the catalog.', enum: PlanStatus::class, example: 'in_active')]
class UpdatePlanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PlanStatus::class)],
        ];
    }
}

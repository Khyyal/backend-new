<?php

namespace Modules\Settlements\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

/**
 * A Saudi IBAN: `SA` followed by 22 digits, spaces ignored.
 */
#[BodyParam('iban', 'string', 'Saudi IBAN: `SA` followed by 22 digits.', example: 'SA0380000000608010167519')]
#[BodyParam('account_name', 'string', 'Account holder (max 255).', example: 'Al Fursan Stables LLC')]
#[BodyParam('bank_name', 'string', 'Bank (max 255).', example: 'Al Rajhi Bank')]
class UpdatePayoutAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('iban'))) {
            $this->merge(['iban' => strtoupper(str_replace(' ', '', $this->input('iban')))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'iban' => ['required', 'string', 'regex:/^SA\d{22}$/'],
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
        ];
    }
}

<?php

namespace Modules\Reservations\Http\Requests\Center;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('end_date', 'string', 'Last day of the stay, `Y-m-d`: from the start date to today, and not after a monthly stay\'s planned end. Default today.', required: false, nullable: true, example: '2026-12-15')]
class EndStablingStayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('editReservations', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function endDate(): ?CarbonImmutable
    {
        return $this->filled('end_date') ? CarbonImmutable::parse($this->validated('end_date')) : null;
    }
}

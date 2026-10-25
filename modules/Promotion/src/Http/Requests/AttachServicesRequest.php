<?php

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Centers\Models\Center;

class AttachServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only services of the route's center can be attached.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'services' => ['required', 'array', 'min:1'],
            'services.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('services', 'id')
                    ->where('center_id', $center->getKey())
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}

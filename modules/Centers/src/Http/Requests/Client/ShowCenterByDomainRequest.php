<?php

namespace Modules\Centers\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\QueryParam;
use Modules\Centers\Http\Requests\Concerns\ParsesCenterIncludes;

/**
 * `include` is a comma-separated list of extras (see INCLUDES).
 */
#[QueryParam('include', 'string', 'Comma-separated extras. `ratings` adds the rating summary and the 10 highest ratings.', required: false, example: 'ratings')]
class ShowCenterByDomainRequest extends FormRequest
{
    use ParsesCenterIncludes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->includeRules();
    }
}

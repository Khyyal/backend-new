<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;

#[BodyParam('name', 'string', 'Center name (max 255).', required: false, example: 'Al Fursan Stables')]
#[BodyParam('city_id', 'integer', 'Existing city ID.', required: false, example: 1)]
#[BodyParam('description', 'string', 'Free-text description.', required: false, nullable: true, example: 'Riding lessons and trail rides for all levels.')]
#[BodyParam('lat', 'number', 'Latitude, -90 to 90. Send with `lng` (both or neither).', required: false, nullable: true, example: 24.7136)]
#[BodyParam('lng', 'number', 'Longitude, -180 to 180. Send with `lat`.', required: false, nullable: true, example: 46.6753)]
#[BodyParam('address', 'string', 'Street address (max 255).', required: false, nullable: true, example: 'King Fahd Rd, Al Olaya')]
#[BodyParam('contact_phone', 'string', 'Public contact phone (max 32).', required: false, example: '+966500000001')]
#[BodyParam('email', 'string', 'Unique contact email (max 255, lower-cased).', required: false, nullable: true, example: 'info@alfursan.sa')]
#[BodyParam('custom_site_enabled', 'boolean', 'Enable the center\'s own website.', required: false, example: false)]
#[BodyParam('domain', 'string', 'Unique custom domain, lower-case hostname such as `alfursan.sa` (max 255).', required: false, nullable: true, example: 'alfursan.sa')]
#[BodyParam('status', 'string', '`visible` lists the center in the client app.', required: false, enum: CenterStatus::class, example: 'visible')]
#[BodyParam('state', 'string', 'Account state; only `active` centers can use center-scoped features.', required: false, enum: CenterState::class, example: 'active')]
#[BodyParam('points', 'integer', 'Popularity score used for the `most_popular` order, 0 or more.', required: false, example: 120)]
#[BodyParam('tags', 'integer[]', 'Full list of distinct existing tag IDs (see the tags endpoints); replaces the current tags.', required: false, example: [4, 7])]
#[BodyParam('logo', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or an existing logo; null removes it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('cover', 'string', 'UUID of a temporary upload or the existing cover; null removes it.', required: false, nullable: true, example: '1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f')]
#[BodyParam('images', 'string[]', 'Full gallery in display order (max 10 distinct UUIDs): existing ones are kept, new temporary uploads are attached, omitted ones are removed.', required: false, example: ['2d3e4f5a-6b7c-4d8e-9f0a-1b2c3d4e5f6a'])]
class UpdateCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('center'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge(['domain' => strtolower(trim($this->input('domain')))]);
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * Partial update. `images` is the full desired list in display order.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'required', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('centers', 'email')->ignore($this->route('center'))],
            'custom_site_enabled' => ['sometimes', 'boolean'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', Rule::unique('centers', 'domain')->ignore($this->route('center'))],
            'status' => ['sometimes', 'required', Rule::enum(CenterStatus::class)],
            'state' => ['sometimes', 'required', Rule::enum(CenterState::class)],
            'points' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}

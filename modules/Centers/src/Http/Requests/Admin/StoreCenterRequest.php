<?php

namespace Modules\Centers\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Enums\CenterState;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Models\Center;

#[BodyParam('name', 'string', 'Center name (max 255).', required: true, example: 'Al Fursan Stables')]
#[BodyParam('city_id', 'integer', 'Existing city ID.', required: true, example: 1)]
#[BodyParam('description', 'string', 'Free-text description.', required: false, nullable: true, example: 'Riding lessons and trail rides for all levels.')]
#[BodyParam('lat', 'number', 'Latitude, -90 to 90. Send with `lng` (both or neither).', required: false, nullable: true, example: 24.7136)]
#[BodyParam('lng', 'number', 'Longitude, -180 to 180. Send with `lat`.', required: false, nullable: true, example: 46.6753)]
#[BodyParam('address', 'string', 'Street address (max 255).', required: false, nullable: true, example: 'King Fahd Rd, Al Olaya')]
#[BodyParam('contact_phone', 'string', 'Public contact phone (max 32).', required: true, example: '+966500000001')]
#[BodyParam('email', 'string', 'Unique contact email (max 255, lower-cased).', required: false, nullable: true, example: 'info@alfursan.sa')]
#[BodyParam('custom_site_enabled', 'boolean', 'Enable the center\'s own website.', required: false, example: false)]
#[BodyParam('domain', 'string', 'Unique custom domain, lower-case hostname such as `alfursan.sa` (max 255).', required: false, nullable: true, example: 'alfursan.sa')]
#[BodyParam('status', 'string', '`visible` lists the center in the client app. Default `invisible`.', required: false, enum: CenterStatus::class, example: 'visible')]
#[BodyParam('state', 'string', 'Account state; only `active` centers can use center-scoped features.', required: false, enum: CenterState::class, example: 'active')]
#[BodyParam('primary_user', 'object', 'Owner account created with the center; its phone is marked verified so the owner can sign in by OTP.', example: ['name' => 'Khalid Al-Harbi', 'phone' => '+966500000002', 'password' => 'secret-password'])]
#[BodyParam('primary_user.name', 'string', 'Owner full name (max 255).', example: 'Khalid Al-Harbi')]
#[BodyParam('primary_user.phone', 'string', 'Owner phone (max 32); must not belong to another center user.', example: '+966500000002')]
#[BodyParam('primary_user.password', 'string', 'Owner password, 8-255 characters (asked on `POST /centers/{center}/access`).', example: 'secret-password')]
#[BodyParam('tags', 'integer[]', 'Full list of distinct existing tag IDs (see the tags endpoints); replaces the current tags.', required: false, example: [4, 7])]
#[BodyParam('logo', 'string', 'UUID of a temporary upload (`POST /api/v1/media`) or an existing logo; null removes it.', required: false, nullable: true, example: '9b1d5f0e-3c1a-4b8e-a0f2-5b7c3e2d1a90')]
#[BodyParam('cover', 'string', 'UUID of a temporary upload or the existing cover; null removes it.', required: false, nullable: true, example: '1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f')]
#[BodyParam('images', 'string[]', 'Full gallery in display order (max 10 distinct UUIDs): existing ones are kept, new temporary uploads are attached, omitted ones are removed.', required: false, example: ['2d3e4f5a-6b7c-4d8e-9f0a-1b2c3d4e5f6a'])]
class StoreCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', Center::class);
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
     * Media fields take stable media ids (see the Support media API).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'description' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('centers', 'email')],
            'custom_site_enabled' => ['sometimes', 'boolean'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', Rule::unique('centers', 'domain')],
            'status' => ['sometimes', Rule::enum(CenterStatus::class)],
            'state' => ['sometimes', Rule::enum(CenterState::class)],
            'primary_user' => ['required', 'array:name,phone,password'],
            'primary_user.name' => ['required', 'string', 'max:255'],
            'primary_user.phone' => ['required', 'string', 'max:32', Rule::unique('center_users', 'phone')],
            'primary_user.password' => ['required', 'string', 'min:8', 'max:255'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'logo' => ['sometimes', 'nullable', 'uuid'],
            'cover' => ['sometimes', 'nullable', 'uuid'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['uuid', 'distinct'],
        ];
    }
}

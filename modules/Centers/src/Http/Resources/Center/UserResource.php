<?php

namespace Modules\Centers\Http\Resources\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

class UserResource extends JsonResource
{
    #[ResponseField('id', 'integer', 'Center user ID.', example: 15)]
    #[ResponseField('name', 'string', 'Full name.', example: 'Khalid Al-Harbi')]
    #[ResponseField('phone', 'string', 'Phone number used for OTP login.', example: '+966500000002')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
        ];
    }
}

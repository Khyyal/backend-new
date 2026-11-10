<?php

namespace Modules\Support\Http\Resources\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

/**
 * A login OTP issued by an admin. The plain code is returned only here, once;
 * the stored copy is hashed.
 *
 * @property array{phone: string, code: string, expires_at: CarbonImmutable} $resource
 */
class IssuedOtpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('phone', 'string', 'Phone the code is valid for.', example: '+966500000003')]
    #[ResponseField('code', 'string', 'The 4-digit code. Shown only in this response.', example: '0427')]
    #[ResponseField('expires_at', 'string', 'When the code expires (ISO-8601).', example: '2026-11-10T09:35:00+00:00')]
    #[ResponseField('expires_in_seconds', 'integer', 'Seconds until the code expires.', example: 300)]
    public function toArray(Request $request): array
    {
        $expiresAt = $this->resource['expires_at'];

        return [
            'phone' => $this->resource['phone'],
            'code' => $this->resource['code'],
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_seconds' => max(0, (int) CarbonImmutable::now()->diffInSeconds($expiresAt)),
        ];
    }
}

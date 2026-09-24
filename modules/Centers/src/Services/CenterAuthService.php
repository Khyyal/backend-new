<?php

namespace Modules\Centers\Services;

use Illuminate\Http\Request;
use Modules\Centers\Models\User;

class CenterAuthService
{
    public function issueAppToken(User $user, ?Request $request = null, ?string $deviceName = null): string
    {
        $fallbackName = $request?->userAgent() ?? 'center-token';

        return $user->createToken($deviceName ?? $fallbackName)->plainTextToken;
    }
}

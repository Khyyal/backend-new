<?php

namespace Modules\Centers\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Centers\Models\User;

class CenterAuthService
{
    public function issueAppToken(User $user, ?Request $request = null, ?string $deviceName = null): string
    {
        $fallbackName = $request?->userAgent() ?? 'center-token';

        return $user->createToken($deviceName ?? $fallbackName)->plainTextToken;
    }

    public function findByPhone(string $phone): ?User
    {
        /** @var User|null */
        return User::query()
            ->where('phone', $phone)
            ->first();
    }

    public function markPhoneVerifiedIfNeeded(User $user): void
    {
        if ($user->phone_verified_at !== null) {
            return;
        }

        $user->forceFill([
            'phone_verified_at' => now(),
        ])->save();
    }

    public function verifyPrimaryAccessPassword(User $user, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        return Hash::check($password, $user->password ?? '');
    }
}


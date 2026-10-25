<?php

namespace Modules\Support\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Support\Models\SystemUser;

class SystemUserAuthService
{
    /**
     * @throws ValidationException When the credentials are wrong or the account is inactive.
     */
    public function attempt(string $email, string $password): SystemUser
    {
        $user = SystemUser::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('support::auth.failed')],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => [__('support::auth.inactive')],
            ]);
        }

        return $user;
    }

    public function issueToken(SystemUser $user, string $deviceName = 'admin'): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}

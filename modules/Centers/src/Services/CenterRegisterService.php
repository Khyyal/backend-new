<?php

namespace Modules\Centers\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Enums\CenterStatus;
use Modules\Centers\Enums\CenterUserRole;
use Modules\Centers\Http\Requests\Center\RegisterCenterFormRequest;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\City;
use Modules\Support\Services\OtpVerificationService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

readonly class CenterRegisterService
{
    public function __construct(
        private OtpVerificationService $otpVerificationService,
        private CenterAuthService      $centerAuthService,
    ) {
    }

    public function register(RegisterCenterFormRequest $request): array
    {
        $phone = $request->input('user.phone');
        $code = $request->input('user.code');

        if (! $this->otpVerificationService->verify($phone, $code)) {
            throw ValidationException::withMessages([
                'user.code' => __('validation.otp_invalid', ['default' => 'Invalid or expired verification code.']),
            ]);
        }

        $result = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('user.name'),
                'phone' => $request->input('user.phone'),
                'phone_verified_at' => now(),
            ]);

            $centerName = $request->input('name');
            $slug = $this->generateUniqueSlug($centerName);

            $cityId = $request->input('city_id') ?? City::query()->value('id');

            $center = Center::create([
                'name' => $centerName,
                'slug' => $slug,
                'city_id' => $cityId,
                'description' => $request->input('description'),
                'contact_phone' => $request->input('contact_phone'),
                'status' => CenterStatus::INVISIBLE,
            ]);

            $center->users()->attach($user->id, [
                'status' => ActivationStatus::ACTIVE->value,
                'joined_at' => now()->toDateString(),
                'is_primary' => true,
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($center->id);

            Role::findOrCreate(CenterUserRole::Owner->value, 'center_user');
            $user->assignRole(CenterUserRole::Owner->value);

            return compact('user', 'center');
        });

        $token = $this->centerAuthService->issueAppToken($result['user'], $request);

        return [
            'user' => $result['user'],
            'center' => $result['center'],
            'token' => $token,
        ];
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'center';
        $slug = $base;
        $counter = 1;

        while (Center::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}

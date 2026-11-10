<?php

namespace Modules\Centers\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Resources\Admin\CenterUserResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Resources\Admin\IssuedOtpResource;
use Modules\Support\Services\OtpVerificationService;

#[Group('Admin / Centers', 'Centers as managed by system users. Requires the matching `centers.*` permission.')]
class CenterUserController extends Controller
{
    #[Endpoint('List center users', 'The center\'s staff accounts, primary user first. Not paginated.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[ApiResponse(['data' => [['id' => 12, 'name' => 'Ahmed Saleh', 'phone' => '0551234567', 'is_primary' => true, 'status' => 'active', 'phone_verified_at' => '2026-10-01T08:00:00+00:00']]], 200, 'The center\'s users.')]
    #[ResponseField('data[].id', 'integer', 'Center user ID.', example: 12)]
    #[ResponseField('data[].name', 'string', 'Name.', example: 'Ahmed Saleh')]
    #[ResponseField('data[].phone', 'string', 'Phone used for OTP login.', example: '0551234567')]
    #[ResponseField('data[].is_primary', 'boolean', 'Whether this is the center\'s primary (owner) user.', example: true)]
    #[ResponseField('data[].status', 'string', 'Assignment status in this center.', example: 'active')]
    #[ResponseField('data[].phone_verified_at', 'string', 'When the phone was verified (ISO-8601).', nullable: true, example: '2026-10-01T08:00:00+00:00')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `centers.view`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('view', $center);

        $users = $center->users()
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('center_users.name')
            ->get();

        return CenterUserResource::collection($users);
    }

    #[Endpoint('Issue a login OTP for a center user', 'Generates a fresh 4-digit login code for the user\'s phone and returns it once so support can read it to them. No SMS is sent; older unused codes for the phone stop working. Valid for 5 minutes. Throttled to 10 requests per minute.')]
    #[UrlParam('center', 'integer', 'Center ID.', example: 3)]
    #[UrlParam('user', 'integer', 'Center user ID. Must belong to the center.', example: 12)]
    #[ApiResponse(['data' => ['phone' => '0551234567', 'code' => '0427', 'expires_at' => '2026-11-10T09:35:00+00:00', 'expires_in_seconds' => 300]], 201, 'The issued code.')]
    #[ResponseField('data.phone', 'string', 'Phone the code is valid for.', example: '0551234567')]
    #[ResponseField('data.code', 'string', 'The 4-digit code. Shown only in this response.', example: '0427')]
    #[ResponseField('data.expires_at', 'string', 'When the code expires (ISO-8601).', example: '2026-11-10T09:35:00+00:00')]
    #[ResponseField('data.expires_in_seconds', 'integer', 'Seconds until the code expires.', example: 300)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `otps.create`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with this ID, or the user does not belong to it.')]
    #[ApiResponse(['message' => 'Too Many Attempts.'], 429, 'More than 10 requests in a minute.')]
    public function issueOtp(Request $request, Center $center, int $user, OtpVerificationService $otps): JsonResponse
    {
        Gate::forUser($request->user())->authorize('createOtp', $center);

        /** @var User $centerUser */
        $centerUser = $center->users()->whereKey($user)->firstOrFail();

        $issued = $otps->issue($centerUser->phone);

        return IssuedOtpResource::make(['phone' => $centerUser->phone, ...$issued])
            ->response()
            ->setStatusCode(201);
    }
}

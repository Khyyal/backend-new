<?php

namespace Modules\Support\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Requests\Admin\LoginRequest;
use Modules\Support\Http\Resources\Admin\SystemUserResource;
use Modules\Support\Services\DeviceService;
use Modules\Support\Services\SystemUserAuthService;

#[Group('Admin / Auth', 'System user (admin panel) authentication with email and password.')]
class AuthController extends Controller
{
    public function __construct(
        private readonly SystemUserAuthService $authService,
        private readonly DeviceService $deviceService,
    ) {}

    #[Unauthenticated]
    #[Endpoint('Log in a system user', 'Checks email and password and issues a Sanctum bearer token for the `admin/*` routes. Inactive accounts cannot log in. Limited to 10 attempts per minute.')]
    #[ApiResponse(['user' => ['id' => 2, 'name' => 'Sara Admin', 'email' => 'sara@khyyal.com', 'status' => 'active', 'roles' => ['support_agent'], 'permissions' => ['centers.view_any', 'centers.view'], 'created_at' => '2026-05-20T09:30:00+00:00', 'updated_at' => '2026-05-21T11:00:00+00:00', 'deleted_at' => null], 'token' => '12|Qm9vb2tpbmdUb2tlbkV4YW1wbGU', 'token_type' => 'Bearer'], 200, 'Authenticated.')]
    #[ResponseField('user', 'object', 'The system user (`SystemUserResource`, unwrapped): id, name, email, status, roles, permissions, timestamps.')]
    #[ResponseField('token', 'string', 'Sanctum plain-text token. Send as `Authorization: Bearer {token}`.', example: '12|Qm9vb2tpbmdUb2tlbkV4YW1wbGU')]
    #[ResponseField('token_type', 'string', 'Always `Bearer`.', example: 'Bearer')]
    #[ApiResponse(['message' => 'These credentials do not match our records.', 'errors' => ['email' => ['These credentials do not match our records.']]], 422, 'Invalid input, wrong credentials, or inactive account (error under `email`).')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many attempts; retry after the `Retry-After` header.')]
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string} $validated */
        $validated = $request->validated();

        $user = $this->authService->attempt($validated['email'], $validated['password']);

        return response()->json([
            'user' => new SystemUserResource($user),
            'token' => $this->authService->issueToken($user),
            'token_type' => 'Bearer',
        ]);
    }

    #[Endpoint('Get the authenticated system user', 'With roles and effective permissions.')]
    #[ResponseFromApiResource(SystemUserResource::class, Modules\Support\Models\SystemUser::class, status: 200, description: 'The current system user.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function me(Request $request): SystemUserResource
    {
        return new SystemUserResource($request->user());
    }

    #[Endpoint('Log out the system user', 'Revokes the bearer token used for this request. When a device identifier is sent (header or body), that device is removed too so it stops receiving push notifications.')]
    #[Header('X-Device-Identifier', 'Optional device ID registered via `POST /api/v1/admin/devices`.', example: 'b3f1c2d4-5e6f-7a8b-9c0d-e1f2a3b4c5d6')]
    #[ApiResponse(status: 204, description: 'Logged out. Empty body.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function logout(Request $request): Response
    {
        $user = $request->user();
        $deviceIdentifier = $this->deviceService->extractDeviceIdentifier($request);

        if ($deviceIdentifier !== null) {
            $this->deviceService->unregister($user, $deviceIdentifier);
        }

        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}

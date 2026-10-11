<?php

namespace Modules\Support\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Support\Http\Requests\Admin\LoginRequest;
use Modules\Support\Http\Resources\Admin\SystemUserResource;
use Modules\Support\Services\SystemUserAuthService;

#[Group(name: 'Admin / Auth', description: 'System user (admin portal) authentication via email + password.')]
class AuthController extends Controller
{
    public function __construct(private readonly SystemUserAuthService $authService) {}

    /**
     * Log in a system user.
     *
     * Verifies the email and password and issues a Sanctum Bearer token.
     * Inactive accounts cannot log in.
     *
     * **Response body:**
     * - `user`       — `SystemUserResource` including roles and permissions.
     * - `token`      — Sanctum plain-text Bearer token.
     * - `token_type` — Always `"Bearer"`.
     */
    #[Response(status: 200, description: 'Authenticated. Returns `user`, `token` and `token_type`.')]
    #[Response(status: 422, description: 'Invalid input, wrong credentials, or inactive account (error keyed under `email`).')]
    #[Response(status: 429, description: 'Too many login attempts. Retry after the period in the `Retry-After` header.')]
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

    /**
     * Get the authenticated system user.
     *
     * Returns the current system user with their roles and effective permissions.
     */
    #[Response(status: 200, description: 'The authenticated system user as `SystemUserResource`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    public function me(Request $request): SystemUserResource
    {
        return new SystemUserResource($request->user());
    }
}

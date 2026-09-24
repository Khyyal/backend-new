<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Http\Requests\Center\CenterAccessRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Http\Resources\Center\CenterWithRoleResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterAuthService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Centers', description: 'Authenticated center user: list centers, view a center, and request center-scoped access credentials.')]
class CenterController extends Controller
{
    public function __construct(
        private readonly CenterAuthService $authService,
    ) {}

    /**
     * List centers the authenticated user has an assignment for.
     *
     * Returns every center with a `center_user_assignment` row for the
     * currently authenticated user. Each item carries the center `id`, its
     * `name`, and the user's Spatie `role` on that center (resolved via
     * team-scoped permissions; typically `"owner"` for the registering user).
     *
     * **Authentication:** Requires a valid Bearer token for the `center_user`
     * guard issued by either `/register` or `/auth/phone/verify`.
     *
     * @param Request $request Current HTTP request used to resolve the user.
     * @return AnonymousResourceCollection 200 collection of
     *                                     `CenterWithRoleResource` items.
     */
    #[Response(
        status: 200,
        description: 'Collection of centers the authenticated user belongs to, each with `id`, `name`, and the user\'s team-scoped `role` (e.g. "owner").'
    )]
    #[Response(
        status: 401,
        description: 'Missing or invalid Bearer token for the `center_user` guard.'
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user('center_user');

        $centers = $user->centers()->get();

        return CenterWithRoleResource::collection($centers);
    }

    /**
     * Fetch a single center the authenticated user is assigned to.
     *
     * Returns the existing `CenterResource` for the given center if and only
     * if the authenticated user has a `center_user_assignment` row for it.
     * No password check is performed here; use `POST /access` to obtain a
     * center-scoped credential.
     *
     * **Authentication:** Requires a valid Bearer token for the `center_user`
     * guard.
     *
     * @param Request $request Current request.
     * @param Center  $center  Center resolved via implicit model binding.
     * @return CenterResource 200 `CenterResource` for the given center.
     */
    #[Response(
        status: 200,
        description: 'The requested center rendered as a `CenterResource`.'
    )]
    #[Response(
        status: 401,
        description: 'Missing or invalid Bearer token for the `center_user` guard.'
    )]
    #[Response(
        status: 403,
        description: 'The authenticated user does not have a center_user_assignment row for this center.'
    )]
    #[Response(
        status: 404,
        description: 'No center exists with the provided id.'
    )]
    public function show(Request $request, Center $center): CenterResource
    {
        /** @var User $user */
        $user = $request->user('center_user');

        if (! $this->hasAssignment($user, $center)) {
            abort(ResponseAlias::HTTP_FORBIDDEN);
        }

        return CenterResource::make($center);
    }

    /**
     * Obtain a center-scoped access credential.
     *
     * Escalates the user's general session into a center-scoped Bearer token.
     * The behavior depends on the user's `is_primary` flag on the
     * `center_user_assignment` pivot:
     *
     * - **Primary user (is_primary = true):** A `password` field must be
     *   provided and must match the user's hashed password. Failure returns
     *   422 keyed on `password`.
     * - **Non-primary user (is_primary = false):** No `password` is required
     *   or checked. The same response shape is returned immediately.
     *
     * In both cases the response contains a freshly minted Sanctum `token`
     * plus the `center` as a `CenterResource`.
     *
     * **Authentication:** Requires a valid Bearer token for the `center_user`
     * guard.
     *
     * **Request body (primary users only):**
     * - `password` — required; the user's account password.
     *
     * **Response body:**
     * - `token`      — Sanctum plain-text Bearer token.
     * - `token_type` — Always `"Bearer"`.
     * - `center`     — `CenterResource` for the selected center.
     *
     * @param CenterAccessRequest $request Validated via {@see CenterAccessRequest}.
     * @param Center              $center  Center resolved via implicit model binding.
     * @return JsonResponse 200 on success with token + center.
     *
     * @throws ValidationException When the user is primary but the password is
     *                             missing or incorrect.
     */
    #[Response(
        status: 200,
        description: 'Center-scoped access granted. Returns a Bearer `token`, `token_type`, and the selected `center` as a `CenterResource`.'
    )]
    #[Response(
        status: 401,
        description: 'Missing or invalid Bearer token for the `center_user` guard.'
    )]
    #[Response(
        status: 403,
        description: 'The authenticated user does not have a center_user_assignment row for this center.'
    )]
    #[Response(
        status: 404,
        description: 'No center exists with the provided id.'
    )]
    #[Response(
        status: 422,
        description: 'For primary users only: `password` is missing, not a string, or does not match the stored hash (error keyed under `password`).'
    )]
    public function access(CenterAccessRequest $request, Center $center): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('center_user');

        if (! $this->hasAssignment($user, $center)) {
            abort(ResponseAlias::HTTP_FORBIDDEN);
        }

        $pivot = $this->getAssignmentPivot($user, $center);
        $isPrimary = (bool) $pivot->is_primary;

        if ($isPrimary) {
            /** @var array{password?: string|null} $validated */
            $validated = $request->validated();
            $password = (string) ($validated['password'] ?? '');

            if ($password === '') {
                throw ValidationException::withMessages([
                    'password' => [__('centers::validation.password_required', ['attribute' => __('centers::auth.password_field')])],
                ]);
            }

            if (! $this->authService->verifyPrimaryAccessPassword($user, $password)) {
                throw ValidationException::withMessages([
                    'password' => [__('centers::auth.invalid_password')],
                ]);
            }
        }

        $token = $this->authService->issueAppToken($user, $request);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'center' => CenterResource::make($center),
        ]);
    }

    private function hasAssignment(User $user, Center $center): bool
    {
        return $user->centers()
            ->where('center_id', $center->id)
            ->exists();
    }

    /**
     * @return \Modules\Centers\Models\CenterUser Pivot model for the (user, center) pair. Assumes hasAssignment returned true.
     */
    private function getAssignmentPivot(User $user, Center $center): \Modules\Centers\Models\CenterUser
    {
        /** @var \Modules\Centers\Models\Center */
        $assigned = $user->centers()
            ->where('center_id', $center->id)
            ->firstOrFail();

        /** @var \Modules\Centers\Models\CenterUser */
        return $assigned->pivot;
    }
}

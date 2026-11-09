<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Center\CenterAccessRequest;
use Modules\Centers\Http\Requests\Center\UpdateCenterRequest;
use Modules\Centers\Http\Requests\Center\UpdateCenterSiteThemeRequest;
use Modules\Centers\Http\Requests\Center\UpdateCenterSocialLinksRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Http\Resources\Center\CenterWithRoleResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\CenterUser;
use Modules\Centers\Models\User;
use Modules\Centers\Services\CenterAuthService;
use Modules\Centers\Services\CenterUpdateService;
use Modules\Support\Documentation\ApiExamples;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group('Center / Centers', 'The centers of the authenticated center user, and center-scoped access tokens.')]
class CenterController extends Controller
{
    public function __construct(
        private readonly CenterAuthService $authService,
    ) {}

    #[Endpoint('List my centers', 'Every center the user is assigned to, with their role and whether they are the primary user. Works with the account-level token.')]
    #[ResponseFromApiResource(CenterWithRoleResource::class, Center::class, status: 200, description: 'The user\'s centers.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user('center_user');

        $centers = $user->centers()->get();

        return CenterWithRoleResource::collection($centers);
    }

    #[Endpoint('Show a center', 'Needs a center-scoped token. Only `show` is allowed while the center is not active.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The center.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not assigned to this center, or the token lacks `center-access:{center}`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
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
     * @throws ValidationException When the user is primary but the password is
     */
    #[Endpoint('Get a center-scoped token', 'Issues a token with the `center-access:{center}` ability required by every `centers/{center}/*` route. The primary user must send their `password`; other users send nothing.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['token' => '32|Q2VudGVyU2NvcGVkVG9rZW4', 'token_type' => 'Bearer', 'center' => ['id' => 8, 'name' => 'Al Fursan Stables', 'slug' => 'al-fursan-stables', 'contact_phone' => '+966500000001', 'email' => 'info@alfursan.sa', 'social_links' => null, 'site_theme' => null, 'custom_site_enabled' => false, 'domain' => null, 'status' => 'invisible', 'state' => 'pending', 'points' => 0, 'address' => null, 'lat' => null, 'lng' => null, 'description' => null]], 200, 'Access granted.')]
    #[ResponseField('token', 'string', 'Center-scoped Sanctum token.', example: '32|Q2VudGVyU2NvcGVkVG9rZW4')]
    #[ResponseField('token_type', 'string', 'Always `Bearer`.', example: 'Bearer')]
    #[ResponseField('center', 'object', 'The center (`CenterResource`).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not assigned to this center.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No center with that ID.')]
    #[ApiResponse(['message' => 'The provided password is incorrect.', 'errors' => ['password' => ['The provided password is incorrect.']]], 422, 'Primary user: password missing or wrong.')]
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

        $token = $this->authService->issueCenterAccessToken($user, $center, $request);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'center' => CenterResource::make($center),
        ]);
    }

    #[Endpoint('Update the center', 'Primary user only. Partial update; social links and the site theme have their own endpoints. `tags` and `images` are full replacement lists.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The updated center with city, tags and media.', with: ['city', 'tags'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the primary user of this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::INVALID_TEMPORARY_MEDIA, 422, 'Validation failed, or a media UUID is invalid, expired or not yours.')]
    public function update(UpdateCenterRequest $request, Center $center, CenterUpdateService $updateService): CenterResource
    {
        /** @var User $user */
        $user = $request->user('center_user');

        return CenterResource::make($updateService->update($center, $user, $request->validated()));
    }

    #[Endpoint('Update the social links', 'Primary user only. Replaces all links.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The updated center.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the primary user of this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The social links field must only contain the following keys: facebook, instagram, whatsapp, tiktok, snapchat, x, youtube, website.', 'errors' => ['social_links' => ['The social links field must only contain the following keys: facebook, instagram, whatsapp, tiktok, snapchat, x, youtube, website.']]], 422, 'Unknown platform or value too long.')]
    public function updateSocialLinks(UpdateCenterSocialLinksRequest $request, Center $center, CenterUpdateService $updateService): CenterResource
    {
        /** @var User $user */
        $user = $request->user('center_user');

        return CenterResource::make($updateService->update($center, $user, $request->validated()));
    }

    #[Endpoint('Update the site theme', 'Primary user only. Replaces the theme.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The updated center.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the primary user of this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The site_theme.primary field must be a valid hexadecimal color.', 'errors' => ['site_theme.primary' => ['The site_theme.primary field must be a valid hexadecimal color.']]], 422, 'Unknown color role or invalid hex color.')]
    public function updateSiteTheme(UpdateCenterSiteThemeRequest $request, Center $center, CenterUpdateService $updateService): CenterResource
    {
        /** @var User $user */
        $user = $request->user('center_user');

        return CenterResource::make($updateService->update($center, $user, $request->validated()));
    }

    private function hasAssignment(User $user, Center $center): bool
    {
        return $user->centers()
            ->where('center_id', $center->id)
            ->exists();
    }

    /**
     * @return CenterUser Pivot model for the (user, center) pair. Assumes hasAssignment returned true.
     */
    private function getAssignmentPivot(User $user, Center $center): CenterUser
    {
        /** @var Center */
        $assigned = $user->centers()
            ->where('center_id', $center->id)
            ->firstOrFail();

        /** @var CenterUser */
        return $assigned->pivot;
    }
}

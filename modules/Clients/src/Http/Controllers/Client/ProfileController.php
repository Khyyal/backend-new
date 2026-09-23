<?php

namespace Modules\Clients\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Clients\Http\Requests\Client\UpdateProfileRequest;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\ClientAuthService;


#[Group(name: 'Client / Profile', description: 'Authenticated client profile management.')]
class ProfileController extends Controller
{
    public function __construct(
        private readonly ClientAuthService $authService,
    )
    {
    }


    /**
     * Update the authenticated client's profile.
     *
     * Sets (or replaces) the client's `first_name` and `last_name`. After a
     * successful update `needs_onboarding` is always `false` because the
     * onboarding requirement is based on the presence of both names.
     *
     * **Authentication:** Requires a valid Bearer token issued by the
     * `/clients/auth/verify` endpoint for the `client` guard.
     *
     * **Request body:**
     * - `first_name` — required, string, 2–255 chars.
     * - `last_name`  — required, string, 2–255 chars.
     *
     * @param UpdateProfileRequest $request Validated via {@see UpdateProfileRequest}.
     * @return JsonResponse 200 with `needs_onboarding`, a confirmation `message`
     *                      and the refreshed `client` resource.
     */
    #[Response(
        status: 200,
        description: 'Profile updated. Returns `needs_onboarding = false`, a localized `message`, and the refreshed `client` resource.'
    )]
    #[Response(
        status: 401,
        description: 'Missing or invalid Bearer token for the `client` guard.'
    )]
    #[Response(
        status: 422,
        description: 'Validation failure on `first_name` or `last_name` (missing, too short, or too long).'
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');
        $data = $request->validated();
        $updated = $this->authService->setClientName($client, $data["first_name"], $data["last_name"]);

        return response()->json([
            'needs_onboarding' => false,
            'message' => __('messages.profile_updated'),
            'client' => ClientResource::make($updated),
        ]);
    }


    /**
     * Fetch the authenticated client's profile.
     *
     * Returns the currently authenticated client (resolved via the `client`
     * guard) as a `ClientResource` with the `city` relationship loaded when
     * a `city_id` is set on the client.
     *
     * **Authentication:** Requires a valid Bearer token for the `client` guard.
     *
     * @param Request $request Current HTTP request used to resolve the user.
     * @return \Modules\Clients\Http\Resources\ClientResource `ClientResource` for the authenticated client.
     */
    #[Response(
        status: 200,
        description: 'Authenticated client profile wrapped in the standard `data` envelope as a `ClientResource` (with `city` loaded when available).'
    )]
    #[Response(
        status: 401,
        description: 'Missing or invalid Bearer token for the `client` guard.'
    )]
    public function me(Request $request): \Modules\Clients\Http\Resources\ClientResource
    {
        /** @var Client $client */
        $client = $request->user('client');
        $client->load('city');
        return ClientResource::make($client);
    }
}

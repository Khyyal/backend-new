<?php

namespace Modules\Clients\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Modules\Clients\Http\Requests\Client\UpdateProfileRequest;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\ClientAuthService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Profile', 'The authenticated client\'s profile.')]
class ProfileController extends Controller
{
    public function __construct(
        private readonly ClientAuthService $authService,
    ) {}

    #[Endpoint('Update my profile', 'Sets the names and optionally the city. Completes onboarding.')]
    #[ApiResponse(['needs_onboarding' => false, 'message' => 'messages.profile_updated', 'client' => ['id' => 77, 'first_name' => 'Noura', 'last_name' => 'Al-Qahtani', 'phone_number' => '+966500000003', 'city_id' => 1, 'city' => ['id' => 1, 'name' => 'Riyadh', 'radius' => 40], 'needs_onboarding' => false]], 200, 'Profile updated.')]
    #[ResponseField('needs_onboarding', 'boolean', 'Always false after a successful update.', example: false)]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.profile_updated')]
    #[ResponseField('client', 'object', 'The updated client (`ClientResource`, with city).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'The first name field must be at least 2 characters.', 'errors' => ['first_name' => ['The first name field must be at least 2 characters.']]], 422, 'Validation failed.')]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');
        $data = $request->validated();
        $updated = $this->authService->updateProfile($client, $data);
        $updated->load('city');

        return response()->json([
            'needs_onboarding' => false,
            'message' => __('messages.profile_updated'),
            'client' => ClientResource::make($updated),
        ]);
    }

    #[Endpoint('Get my profile', 'The authenticated client, with city.')]
    #[ResponseFromApiResource(ClientResource::class, Client::class, status: 200, description: 'The client.', with: ['city'])]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    public function me(Request $request): ClientResource
    {
        /** @var Client $client */
        $client = $request->user('client');
        $client->load('city');

        return ClientResource::make($client);
    }
}

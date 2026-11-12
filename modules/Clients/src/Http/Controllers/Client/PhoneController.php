<?php

namespace Modules\Clients\Http\Controllers\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Clients\Http\Requests\Client\ChangePhoneRequest;
use Modules\Clients\Http\Requests\Client\VerifyPhoneChangeRequest;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\ClientAuthService;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Services\OtpVerificationService;

#[Group('Client / Profile', 'The authenticated client\'s profile.')]
class PhoneController extends Controller
{
    public function __construct(
        private readonly OtpVerificationService $verification,
        private readonly ClientAuthService $authService,
    ) {}

    #[Endpoint('Request a phone change code', 'Sends a 4-digit code by SMS to the new phone. Limited to 5 codes per phone every 15 minutes. Then call `POST /api/v1/clients/phone/verify`.')]
    #[ApiResponse(['message' => 'messages.otp_sent', 'expires_in_seconds' => 300], 200, 'Code sent.')]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.otp_sent')]
    #[ResponseField('expires_in_seconds', 'integer', 'Code lifetime in seconds.', example: 300)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'validation.phone_taken', 'errors' => ['phone_number' => ['validation.phone_taken']]], 422, 'Invalid phone, the phone is already yours (`validation.phone_same`), or it belongs to another client (`validation.phone_taken`).')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many codes for this phone; retry after `Retry-After`.')]
    public function send(ChangePhoneRequest $request): JsonResponse
    {
        $this->verification->send((string) $request->validated('phone_number'));

        return response()->json([
            'message' => __('messages.otp_sent'),
            'expires_in_seconds' => OtpVerificationService::DEFAULT_TTL_SECONDS,
        ]);
    }

    /**
     * @throws ValidationException When the OTP code is incorrect.
     */
    #[Endpoint('Confirm the phone change', 'Checks the code sent to the new phone and makes it the client\'s phone. The current token stays valid; the next login uses the new phone.')]
    #[ApiResponse(['message' => 'messages.phone_updated', 'client' => ['id' => 77, 'first_name' => 'Noura', 'last_name' => 'Al-Qahtani', 'phone_number' => '+966500000004', 'city_id' => 1, 'city' => ['id' => 1, 'name' => 'Riyadh', 'radius' => 40], 'needs_onboarding' => false]], 200, 'Phone changed.')]
    #[ResponseField('message', 'string', 'Confirmation message key.', example: 'messages.phone_updated')]
    #[ResponseField('client', 'object', 'The updated client (`ClientResource`, with city).')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(['message' => 'messages.invalid_otp', 'errors' => ['code' => ['messages.invalid_otp']]], 422, 'Validation failed, the phone is taken, or the code is wrong or expired.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many wrong codes for this phone; retry after `Retry-After`.')]
    public function verify(VerifyPhoneChangeRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user('client');
        $phone = (string) $request->validated('phone_number');

        if (! $this->verification->verify($phone, (string) $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => [__('messages.invalid_otp')],
            ]);
        }

        $updated = $this->authService->changePhone($client, $phone);
        $updated->load('city');

        return response()->json([
            'message' => __('messages.phone_updated'),
            'client' => ClientResource::make($updated),
        ]);
    }
}

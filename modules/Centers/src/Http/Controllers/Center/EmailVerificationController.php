<?php

namespace Modules\Centers\Http\Controllers\Center;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Http\Requests\Center\SendCenterEmailVerificationRequest;
use Modules\Centers\Http\Requests\Center\VerifyCenterEmailRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Models\Center;
use Modules\Centers\Services\CenterEmailVerification;
use Modules\Support\Documentation\ApiExamples;

#[Group('Center / Profile', 'How complete the center profile is and what is left to do.')]
class EmailVerificationController extends Controller
{
    public function __construct(private readonly CenterEmailVerification $verification) {}

    #[Endpoint('Send the email verification code', 'Primary user only. Emails a 6-digit code (valid 10 minutes) to the center\'s contact email. One code per minute and 5 per 15 minutes.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ApiResponse(['message' => 'Verification code sent to your email.', 'expires_in_seconds' => 600], 200, 'Code sent.')]
    #[ResponseField('message', 'string', 'Localized confirmation.', example: 'Verification code sent to your email.')]
    #[ResponseField('expires_in_seconds', 'integer', 'Code lifetime in seconds.', example: 600)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the primary user of this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'Add a contact email to the center first.', 'errors' => ['email' => ['Add a contact email to the center first.']]], 422, 'The center has no email, or it is already verified.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Code requested too soon or too often; retry after `Retry-After`.')]
    public function send(SendCenterEmailVerificationRequest $request, Center $center): JsonResponse
    {
        $this->verification->send($center, app()->getLocale());

        return response()->json([
            'message' => __('centers::email_verification.sent'),
            'expires_in_seconds' => CenterEmailVerification::TTL_MINUTES * 60,
        ]);
    }

    #[Endpoint('Verify the center email', 'Primary user only. Confirms the contact email with the emailed code; 5 wrong attempts per 5 minutes. Changing the email later clears the verification.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterResource::class, Center::class, status: 200, description: 'The center with `email_verified_at` set.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not the primary user of this center. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'Invalid or expired verification code.', 'errors' => ['code' => ['Invalid or expired verification code.']]], 422, 'Validation failed, the code is wrong or expired, or the center has no email / is already verified.')]
    #[ApiResponse(ApiExamples::THROTTLED, 429, 'Too many wrong codes; retry after `Retry-After`.')]
    public function verify(VerifyCenterEmailRequest $request, Center $center): CenterResource
    {
        return CenterResource::make($this->verification->verify($center, $request->validated('code')));
    }
}

<?php

namespace Modules\Clients\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Clients\Models\Client;
use Modules\Support\Documentation\ApiExamples;
use Modules\Support\Http\Resources\Admin\IssuedOtpResource;
use Modules\Support\Services\OtpVerificationService;

#[Group('Admin / Clients', 'Every client across all centers. Requires the matching `clients.*` permission.')]
class ClientOtpController extends Controller
{
    #[Endpoint('Issue a login OTP', 'Generates a fresh 4-digit login code for the client\'s phone and returns it once so support can read it to the client. No SMS is sent; older unused codes for the phone stop working. Valid for 5 minutes. Throttled to 10 requests per minute.')]
    #[UrlParam('client', 'integer', 'Client ID.', example: 77)]
    #[ApiResponse(['data' => ['phone' => '+966500000003', 'code' => '0427', 'expires_at' => '2026-11-10T09:35:00+00:00', 'expires_in_seconds' => 300]], 201, 'The issued code.')]
    #[ResponseField('data.phone', 'string', 'Phone the code is valid for.', example: '+966500000003')]
    #[ResponseField('data.code', 'string', 'The 4-digit code. Shown only in this response.', example: '0427')]
    #[ResponseField('data.expires_at', 'string', 'When the code expires (ISO-8601).', example: '2026-11-10T09:35:00+00:00')]
    #[ResponseField('data.expires_in_seconds', 'integer', 'Seconds until the code expires.', example: 300)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Missing `otps.create`.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No client with this ID.')]
    #[ApiResponse(['message' => 'Too Many Attempts.'], 429, 'More than 10 requests in a minute.')]
    public function store(Request $request, Client $client, OtpVerificationService $otps): JsonResponse
    {
        Gate::forUser($request->user())->authorize('createOtp', $client);

        $issued = $otps->issue($client->phone_number);

        return IssuedOtpResource::make(['phone' => $client->phone_number, ...$issued])
            ->response()
            ->setStatusCode(201);
    }
}

<?php

namespace Modules\Centers\Http\Controllers\Center;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Centers\Http\Requests\Center\RegisterCenterFormRequest;
use Modules\Centers\Http\Resources\Center\CenterResource;
use Modules\Centers\Http\Resources\Center\UserResource;
use Modules\Centers\Services\CenterRegisterService;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

#[Group(name: 'Center / Register', description: 'Center registration')]
class RegisterController extends Controller
{

    #[Response(
        status: 201,
        description: 'Registration succeeded. Returns the created `user`, the new `center` record, and a `token` Bearer token for immediate authentication.'
    )]
    #[Response(
        status: 422,
        description: 'Validation failure on any request field, or the OTP `user.code` is invalid/expired (error keyed under `user.code`).'
    )]
    #[Response(
        status: 429,
        description: 'Too many registration attempts. Retry after the period returned in the `Retry-After` header (10 req/min per IP).'
    )]
    public function store(
        RegisterCenterFormRequest $request,
        CenterRegisterService $service,
    ): JsonResponse {
        $result = $service->register($request);

        return response()->json([
            'user' => new UserResource($result['user']),
            'center' => new CenterResource($result['center']),
            'token' => $result['token'],
        ], ResponseAlias::HTTP_CREATED);
    }
}

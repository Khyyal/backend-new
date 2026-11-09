<?php

namespace Modules\Services\Http\Controllers\Center;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Http\Requests\Center\ServiceTypeTermRequest;
use Modules\Services\Http\Resources\Center\ServiceTypeTermResource;
use Modules\Services\Models\ServiceTypeTerm;
use Modules\Services\Services\ServiceTypeTermService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Service type terms', 'Terms and conditions a center sets per service type. Served on `centers/{center}/…` (center-scoped token; center users need `a `services.*.manage` permission`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class ServiceTypeTermController extends Controller
{
    public function __construct(private readonly ServiceTypeTermService $service) {}

    #[Endpoint('List terms per service type', 'One entry for each of `recreation_riding`, `visit`, `event` and `resort`; `terms` is null when none is defined.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(ServiceTypeTermResource::class, ServiceTypeTerm::class, status: 200, description: 'Terms per type.', collection: true)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not allowed to manage this center\'s services. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        return ServiceTypeTermResource::collection($this->service->listForCenter($center));
    }

    #[Endpoint('Set the terms of a service type', 'Upsert: 201 when created, 200 when existing terms are replaced.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[UrlParam('type', 'string', 'Service type.', example: 'visit')]
    #[ResponseFromApiResource(ServiceTypeTermResource::class, ServiceTypeTerm::class, status: 201, description: 'Terms created.')]
    #[ResponseFromApiResource(ServiceTypeTermResource::class, ServiceTypeTerm::class, status: 200, description: 'Terms replaced.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not allowed to manage this center\'s services. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, '`type` is not a service type.')]
    #[ApiResponse(['message' => 'The terms.ar field is required.', 'errors' => ['terms.ar' => ['The terms.ar field is required.']]], 422, 'Validation failed.')]
    public function updateTerms(ServiceTypeTermRequest $request, Center $center, ServiceType $type): ServiceTypeTermResource
    {
        return ServiceTypeTermResource::make(
            $this->service->upsert($center, $type, $request->validated())
        );
    }
}

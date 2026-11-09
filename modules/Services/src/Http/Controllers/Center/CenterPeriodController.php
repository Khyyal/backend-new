<?php

namespace Modules\Services\Http\Controllers\Center;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Centers\Models\Center;
use Modules\Services\Http\Requests\Center\CenterPeriodRequest;
use Modules\Services\Http\Resources\Center\CenterPeriodResource;
use Modules\Services\Services\CenterPeriodService;
use Modules\Support\Documentation\ApiExamples;

#[Group('Periods', 'Female and male periods of a center: weekdays and hours reserved for each gender, applied to all its services. A center may define one, both or neither. Served on `centers/{center}/…` (center-scoped token; center users need `a `services.*.manage` permission`) and `admin/centers/{center}/…` (system users need `centers.manage_services`).')]
class CenterPeriodController extends Controller
{
    public function __construct(private readonly CenterPeriodService $periods) {}

    #[Endpoint('Show the periods')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterPeriodResource::class, Center::class, status: 200, description: 'The periods.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not allowed to manage this center\'s services. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    public function show(Request $request, Center $center): CenterPeriodResource
    {
        Gate::forUser($request->user())->authorize('manageServices', $center);

        return CenterPeriodResource::make($center->load('periods'));
    }

    #[Endpoint('Replace the periods', 'A gender sent as null (or omitted) is cleared.')]
    #[UrlParam('center', 'integer', 'Center ID. Must match the center the access token was issued for.', example: 8)]
    #[ResponseFromApiResource(CenterPeriodResource::class, Center::class, status: 200, description: 'The updated periods.')]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::FORBIDDEN, 403, 'Not allowed to manage this center\'s services. Also returned when the token lacks `center-access:{center}` or the center is not active.')]
    #[ApiResponse(['message' => 'The female.hours field is required when female is present.', 'errors' => ['female.hours' => ['The female.hours field is required when female is present.']]], 422, 'Validation failed.')]
    public function update(CenterPeriodRequest $request, Center $center): CenterPeriodResource
    {
        return CenterPeriodResource::make($this->periods->sync($center, $request->validated()));
    }
}

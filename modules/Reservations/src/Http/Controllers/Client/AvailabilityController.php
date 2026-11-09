<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ApiResponse;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Reservations\Http\Requests\Client\AvailabilityRequest;
use Modules\Reservations\Services\AvailabilityService;
use Modules\Services\Models\Service;
use Modules\Support\Documentation\ApiExamples;

#[Group('Client / Reservations', 'Book, list and cancel reservations, and buy and spend packages and memberships.')]
class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    #[Endpoint('Show a service calendar', 'One entry per day of `from`..`to` (max 31 days). Every day has `date`, `day_of_week`, `is_past`, `blocked`, `blocked_periods` and informational gender `periods`. Riding and training add `capacity_per_hour`, `windows` and `booked`; visits and events add `open`, `capacity`, `reserved`, `available` (visits also `windows`); resorts add `open`, `price`, `reserved`.')]
    #[UrlParam('service', 'integer', 'Service ID.', example: 31)]
    #[ApiResponse(['data' => [['date' => '2026-06-05', 'day_of_week' => 5, 'is_past' => false, 'blocked' => false, 'blocked_periods' => [], 'periods' => [['gender' => 'female', 'start' => '16:00', 'end' => '18:00']], 'capacity_per_hour' => 5, 'windows' => [['start' => '16:00', 'end' => '20:00']], 'booked' => [['start' => '16:00', 'end' => '17:00', 'quantity' => 3]]]]], 200, 'Days.')]
    #[ResponseField('data[].date', 'string', 'Day, `Y-m-d`.', example: '2026-06-05')]
    #[ResponseField('data[].day_of_week', 'integer', '0 = Sunday … 6 = Saturday.', example: 5)]
    #[ResponseField('data[].is_past', 'boolean', 'The day is over.', example: false)]
    #[ResponseField('data[].blocked', 'boolean', 'Blocked all day.', example: false)]
    #[ResponseField('data[].blocked_periods', 'object[]', 'Blocked `start`/`end` hours.', example: [])]
    #[ResponseField('data[].periods', 'object[]', 'Gender periods: `gender`, `start`, `end`.', example: [['gender' => 'female', 'start' => '16:00', 'end' => '18:00']])]
    #[ResponseField('data[].capacity_per_hour', 'integer', 'Riding/training: hourly limit; null for none.', required: false, nullable: true, example: 5)]
    #[ResponseField('data[].windows', 'object[]', 'Schedule windows `start`/`end`.', required: false, example: [['start' => '16:00', 'end' => '20:00']])]
    #[ResponseField('data[].booked', 'object[]', 'Riding/training: booked slots `start`, `end`, `quantity`.', required: false, example: [['start' => '16:00', 'end' => '17:00', 'quantity' => 3]])]
    #[ResponseField('data[].open', 'boolean', 'Visits/events/resorts: bookable that day.', required: false, example: true)]
    #[ResponseField('data[].capacity', 'integer', 'Visits/events: daily limit; null for none.', required: false, nullable: true, example: 100)]
    #[ResponseField('data[].reserved', 'integer', 'Visits/events: reserved count. Resorts: boolean, already reserved.', required: false, example: 20)]
    #[ResponseField('data[].available', 'integer', 'Visits/events: tickets left; null without a limit.', required: false, nullable: true, example: 80)]
    #[ResponseField('data[].price', 'integer', 'Resorts: day price in halalas; null when not priced.', required: false, nullable: true, example: 120000)]
    #[ApiResponse(ApiExamples::UNAUTHENTICATED, 401, 'Missing or invalid bearer token.')]
    #[ApiResponse(ApiExamples::NOT_FOUND, 404, 'No such service.')]
    #[ApiResponse(['message' => 'A trainer must be selected for this service.', 'reason' => 'trainer_required', 'errors' => ['reservation' => ['A trainer must be selected for this service.']]], 422, 'Invalid range, or the service cannot be reserved (`reason`: service_inactive, service_type_not_bookable, trainer_required, invalid_trainer).')]
    public function show(AvailabilityRequest $request, int $service): JsonResponse
    {
        $service = Service::query()->with(['serviceable', 'center'])->findOrFail($service);

        return response()->json([
            'data' => $this->availability->forService(
                $service,
                CarbonImmutable::parse($request->validated('from')),
                CarbonImmutable::parse($request->validated('to')),
                $request->filled('trainer_id') ? (int) $request->validated('trainer_id') : null,
            ),
        ]);
    }
}

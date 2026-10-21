<?php

namespace Modules\Reservations\Http\Controllers\Client;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reservations\Http\Requests\Client\AvailabilityRequest;
use Modules\Reservations\Services\AvailabilityService;
use Modules\Services\Models\Service;

#[Group(name: 'Client / Reservations', description: 'Book, list and cancel reservations, and buy and spend service packages. Requires a client Bearer token.')]
class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Show a service's calendar.
     *
     * One entry per day of `from`..`to` (at most 31 days). Every day has `blocked`, `blocked_periods`
     * and the informational gender `periods`. Hourly services (riding, training) add `windows`,
     * `capacity_per_hour` and `booked` slots; visits and events add `open`, `capacity`, `reserved`
     * and `available`; resorts add `open`, `price` and `reserved`.
     *
     * **Query:** `from`, `to` (`Y-m-d`), and `trainer_id` (required for training services).
     */
    #[Response(status: 200, description: '`{data: day[]}`.')]
    #[Response(status: 401, description: 'Missing or invalid Bearer token.')]
    #[Response(status: 404, description: 'No such service.')]
    #[Response(status: 422, description: 'Invalid range, or the service cannot be reserved (see `reason`).')]
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

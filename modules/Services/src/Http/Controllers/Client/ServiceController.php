<?php

namespace Modules\Services\Http\Controllers\Client;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Routing\Controller;
use Modules\Reservations\Services\Availability\UpcomingAvailabilityService;
use Modules\Services\Http\Resources\Client\ServiceDetailsResource;
use Modules\Services\Services\ServiceDetailsService;

#[Group(name: 'Client / Services', description: 'Browse the services of a center. Public, no authentication required.')]
class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceDetailsService $service,
        private readonly UpcomingAvailabilityService $availability,
    ) {}

    /**
     * Show a service by its slug.
     *
     * Slugs are unique per center. Only `active` services of `visible`
     * centers are returned. `type` is the service type and `details` its
     * type-specific shape (e.g. `days` for resorts, `trainers` for training,
     * `days` and `hours` for recreation riding and visits).
     *
     * Recreation riding `details` include `availability`, and each of a
     * training service's `details.trainers` (`{id, name, availability}`)
     * its own, computed from that trainer's schedules and hourly limit
     * (shared by every service the trainer teaches). Availability covers
     * today and the next 14 days, each `{date, available, hours}`; `hours` are the
     * one-hour slots of the day's schedule windows, each `{hour, available,
     * available_tickets, max_tickets}` (`available_tickets` / `max_tickets`
     * are `null` when there is no hourly limit). A slot is available when it has not
     * started, is not blocked, has tickets left and the service accepts
     * bookings; a day is available when any of its slots is.
     *
     * Event `details.availability` is empty for `general` events. For
     * `specific_day` events it lists every event day (`start_date` ..
     * `end_date`) as `{day, available, available_tickets, max_tickets}`
     * under `max_tickets_per_day`; a day is available while booking is
     * open today (`open_date` .. `close_date`), the day has not passed, is
     * not blocked all day and has tickets left.
     */
    #[Response(status: 200, description: '`{data: {type, details}}`.')]
    #[Response(status: 404, description: 'No such active service in this visible center.')]
    public function show(int $center, string $slug): ServiceDetailsResource
    {
        $service = $this->service->findBySlugForCenter($center, $slug);

        return ServiceDetailsResource::make($service)
            ->withAvailability($this->availability->forService($service));
    }
}

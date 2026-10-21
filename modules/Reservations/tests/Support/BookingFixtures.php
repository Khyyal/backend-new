<?php

namespace Modules\Reservations\Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Centers\Models\Center;
use Modules\Clients\Models\Client;
use Modules\Reservations\Booking\BookingQuote;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\ReservationRulesResolver;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;

/**
 * Builders shared by the booking rules tests.
 */
class BookingFixtures
{
    /**
     * Wraps a serviceable model in an active Service of the given center.
     */
    public static function service(Center $center, Model $serviceable, ServiceType $type): Service
    {
        return $serviceable->service()->create([
            'name' => ['ar' => 'خدمة', 'en' => 'Service'],
            'description' => ['ar' => 'وصف', 'en' => 'Description'],
            'center_id' => $center->getKey(),
            'status' => ActivationStatus::ACTIVE,
            'type' => $type,
        ]);
    }

    /**
     * An active riding open on Mondays 09:00-12:00, with a 60-minute option at
     * 100 and every booking setting enabled.
     */
    public static function riding(Center $center, int $maxTicketsPerHour = 4): Service
    {
        $riding = RecreationalRiding::query()->create(['max_tickets_per_hour' => $maxTicketsPerHour]);
        $service = self::service($center, $riding, ServiceType::RecreationRiding);
        $service->priceOptions()->create(['price' => 100, 'quantity' => 60, 'unit' => PriceOptionUnit::MINUTE]);
        self::enableSettings($service, ['enable_single_session_booking', 'enable_package_booking', 'enable_cash_on_arrival']);
        self::schedule($riding, 1, '09:00', '12:00');

        return $service->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides  named BookingRequest arguments
     */
    public static function request(Service $service, Client $client, string $date, array $overrides = []): BookingRequest
    {
        return new BookingRequest(...[
            'service' => $service->fresh(),
            'client' => $client,
            'date' => CarbonImmutable::parse($date),
            ...$overrides,
        ]);
    }

    /**
     * @param  array<int, string>  $keys
     */
    public static function enableSettings(Service $service, array $keys): void
    {
        foreach ($keys as $key) {
            $service->settings()->updateOrCreate(['key' => $key], ['value' => '1']);
        }
    }

    public static function schedule(Model $schedulable, int $dayOfWeek, ?string $start, ?string $end): Schedule
    {
        return $schedulable->schedules()->create([
            'day_of_week' => $dayOfWeek,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides  named BookingRequest arguments
     */
    public static function quote(Service $service, Client $client, string $date, array $overrides = []): BookingQuote
    {
        return app(ReservationRulesResolver::class)->quote(self::request($service, $client, $date, $overrides));
    }
}

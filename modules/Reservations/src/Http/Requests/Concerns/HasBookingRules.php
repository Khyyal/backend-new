<?php

namespace Modules\Reservations\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Modules\Clients\Models\Client;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Services\Models\Service;

/**
 * Validation shared by the requests that book a slot. Whether the slot is
 * actually bookable is decided afterwards by the booking rules (422 with a
 * `reason`); these rules only check the shape of the input.
 */
trait HasBookingRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function slotRules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'trainer_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function bookingRules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            ...$this->slotRules(),
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'price_option_id' => ['nullable', 'integer'],
            'lines' => ['sometimes', 'array', 'max:20'],
            'lines.*.price_option_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function paymentCompanyRules(): array
    {
        return [
            'payment_company' => ['nullable', 'string', Rule::in(self::availablePaymentCompanies())],
        ];
    }

    /**
     * The default gateway plus every enabled provider.
     *
     * @return array<int, string>
     */
    public static function availablePaymentCompanies(): array
    {
        $enabled = collect((array) config('purchase.providers', []))
            ->filter(fn (array $provider): bool => (bool) ($provider['enabled'] ?? false))
            ->keys();

        return $enabled->push((string) config('purchase.default_gateway', 'fake'))->unique()->values()->all();
    }

    public function toBookingRequest(Service $service, Client $client): BookingRequest
    {
        $endDate = $this->validated('end_date');

        return new BookingRequest(
            service: $service,
            client: $client,
            date: CarbonImmutable::parse($this->validated('date')),
            startTime: $this->validated('start_time'),
            endDate: $endDate !== null ? CarbonImmutable::parse($endDate) : null,
            quantity: (int) $this->validated('quantity', 1),
            priceOptionId: $this->filled('price_option_id') ? (int) $this->validated('price_option_id') : null,
            trainerId: $this->filled('trainer_id') ? (int) $this->validated('trainer_id') : null,
            lines: array_map(fn (array $line): array => [
                'price_option_id' => (int) $line['price_option_id'],
                'quantity' => (int) $line['quantity'],
            ], (array) $this->validated('lines', [])),
        );
    }
}

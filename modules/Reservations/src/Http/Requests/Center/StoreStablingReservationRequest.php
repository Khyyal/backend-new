<?php

namespace Modules\Reservations\Http\Requests\Center;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\Horse;
use Modules\Reservations\Booking\BookingRequest;
use Modules\Reservations\Booking\DiscountInput;
use Modules\Services\Models\Service;

/**
 * Center staff put a client's horse into a stabling service.
 */
#[BodyParam('horse_id', 'integer', 'A client-owned horse of this center; its owner is the reservation\'s client.', example: 5)]
#[BodyParam('service_id', 'integer', 'Stabling service ID (`service_id` of the stabling resource).', example: 31)]
#[BodyParam('price_option_id', 'integer', 'A monthly price (the stay covers its months from `start_date`) or the daily price (an open-ended stay, charged one day).', example: 70)]
#[BodyParam('start_date', 'string', 'First day of the stay, `Y-m-d`, today or later.', example: '2026-12-01')]
#[BodyParam('addon_ids', 'integer[]', 'Extras of the service, each added once to the stay (max 20, distinct).', required: false, example: [72])]
#[BodyParam('total_amount', 'integer', 'Total to charge in halalas, replacing the computed price; offers and coupons are not applied. 0 confirms without a purchase.', required: false, nullable: true, example: 140000)]
#[BodyParam('is_paid', 'boolean', 'true: already paid at the desk (payment recorded, everything confirmed); false: confirmed now, payment pending (collected later).', example: true)]
#[BodyParam('notes', 'string', 'Staff notes (max 1000).', required: false, nullable: true, example: 'Needs a corner stall.')]
class StoreStablingReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageReservations', $this->route('center'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'horse_id' => ['required', 'integer', Rule::exists('horses', 'id')->where('center_id', $center->getKey())->withoutTrashed()],
            'service_id' => ['required', 'integer'],
            'price_option_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'addon_ids' => ['sometimes', 'array', 'max:20'],
            'addon_ids.*' => ['integer', 'distinct'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'is_paid' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function totalOverride(): ?int
    {
        return $this->filled('total_amount') ? (int) $this->validated('total_amount') : null;
    }

    public function toBookingRequest(Service $service, Horse $horse): BookingRequest
    {
        return new BookingRequest(
            service: $service,
            client: $horse->client,
            date: CarbonImmutable::parse($this->validated('start_date')),
            priceOptionId: (int) $this->validated('price_option_id'),
            lines: array_map(fn (int|string $id): array => [
                'price_option_id' => (int) $id,
                'quantity' => 1,
            ], (array) $this->validated('addon_ids', [])),
            horseId: $horse->getKey(),
            totalOverride: $this->totalOverride(),
        );
    }

    public function toDiscountInput(): DiscountInput
    {
        return new DiscountInput(applyPromotions: $this->totalOverride() === null);
    }
}

<?php

namespace Modules\Services\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;

/**
 * A service with the details of its serviceable, shaped by the type's own
 * resource. Upcoming availability is handed to the resources of the types
 * that support it.
 *
 * @property Service $resource
 */
class ServiceDetailsResource extends JsonResource
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $availability = null;

    /**
     * @param  array<int, array<string, mixed>>|null  $availability
     */
    public function withAvailability(?array $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    #[ResponseField('type', 'string', 'Service type; decides the shape of `details`.', enum: ServiceType::class, example: 'recreation_riding')]
    #[ResponseField('details', 'object', 'Type-specific service: the client `RecreationRidingResource` (with `availability`), `VisitResource`, `EventResource` (with `availability`), `ResortResource`, `HorseCareResource`, `HorseServiceResource`, `TrainingServiceResource` (trainers with `availability`) or `MembershipResource`. Every shape has id, service_id, slug, name, description, cover and images.', example: ['id' => 12, 'service_id' => 31, 'slug' => 'beginner-ride', 'name' => ['ar' => 'ركوب للمبتدئين', 'en' => 'Beginner ride'], 'description' => ['ar' => 'جولة بإشراف مدرب للمبتدئين.', 'en' => 'A guided ride for first-timers.'], 'max_tickets_per_hour' => 5, 'settings' => ['enable_cash_on_arrival' => true, 'enable_single_session_booking' => true, 'enable_package_booking' => false], 'price_options' => [['id' => 70, 'duration' => 60, 'price' => 15000, 'package' => null]], 'days' => [5, 6], 'hours' => ['16:00', '20:00'], 'availability' => [['date' => '2026-06-05', 'available' => true, 'hours' => [['hour' => '16:00', 'available' => true, 'available_tickets' => 3, 'max_tickets' => 5]]]], 'cover' => null, 'images' => []])]
    #[ResponseField('start_price', 'integer', 'Lowest price in halalas; null when the service has no price option. Resort add-ons are not counted.', nullable: true, example: 15000)]
    #[ResponseField('display_price', 'object', 'Set only while an automatic offer lowers the start price: `original`, `discounted`, `discount_amount` (halalas), `badge`.', nullable: true, example: ['original' => 15000, 'discounted' => 12750, 'discount_amount' => 2250, 'badge' => '-15%'])]
    public function toArray(Request $request): array
    {
        $serviceable = $this->resource->serviceable;
        $startPrice = $this->resource->getAttribute('start_price');

        return [
            'type' => $this->resource->type,
            'start_price' => $startPrice !== null ? (int) $startPrice : null,
            'display_price' => $this->resource->getAttribute('display_price'),
            'details' => match ($this->resource->type) {
                ServiceType::RecreationRiding => RecreationRidingResource::make($serviceable)->withAvailability($this->availability),
                ServiceType::Visit => VisitResource::make($serviceable),
                ServiceType::Event => EventResource::make($serviceable)->withAvailability($this->availability),
                ServiceType::Resort => ResortResource::make($serviceable),
                ServiceType::HorseCare => HorseCareResource::make($serviceable),
                ServiceType::HorseService => HorseServiceResource::make($serviceable),
                ServiceType::Training => TrainingServiceResource::make($serviceable)->withAvailability($this->availability),
                ServiceType::Membership => MembershipResource::make($serviceable),
            },
        ];
    }
}

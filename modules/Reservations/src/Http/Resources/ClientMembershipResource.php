<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Models\ClientMembership;

/**
 * @property ClientMembership $resource
 */
class ClientMembershipResource extends JsonResource
{
    /**
     * Free visits already used today, when counted by the caller.
     */
    private ?int $visitsUsedToday = null;

    public function withVisitsUsedToday(?int $visitsUsedToday): static
    {
        $this->visitsUsedToday = $visitsUsedToday;

        return $this;
    }

    #[ResponseField('id', 'integer', 'Client membership ID.', example: 9)]
    #[ResponseField('status', 'string', '`pending` until paid, then `active`, `expired` or `cancelled`.', enum: ClientMembershipStatus::class, example: 'active')]
    #[ResponseField('service', 'object', 'The membership service (`id`, `name` by locale, `color`). Present when loaded.', required: false, example: ['id' => 40, 'name' => ['ar' => 'عضوية ذهبية', 'en' => 'Gold membership'], 'color' => '#D4AF37'])]
    #[ResponseField('membership_id', 'integer', 'The membership bought.', example: 3)]
    #[ResponseField('price', 'integer', 'Price paid in halalas.', example: 250000)]
    #[ResponseField('duration', 'integer', 'Validity in months.', example: 6)]
    #[ResponseField('visit_service', 'object', 'Visit service with free entries (`id`, `name` by locale); null when none. Present when loaded.', required: false, nullable: true, example: ['id' => 33, 'name' => ['ar' => 'زيارة', 'en' => 'Visit']])]
    #[ResponseField('max_visits_per_day', 'integer', 'Free visits per day; null without a visit service.', nullable: true, example: 1)]
    #[ResponseField('visits_left_today', 'integer', 'Free visits left today. Only when the membership grants visits and usage was computed.', required: false, example: 1)]
    #[ResponseField('packages', 'object[]', 'Included packages (`ClientPackageResource`). Present when loaded.', required: false)]
    #[ResponseField('expires_at', 'string', 'Expiry (ISO-8601); null until paid.', nullable: true, example: '2026-12-01T00:00:00+00:00')]
    #[ResponseField('is_usable', 'boolean', 'Active and not expired.', example: true)]
    #[ResponseField('created_at', 'string', 'Purchase time (ISO-8601).', example: '2026-05-20T09:30:00+00:00')]
    #[ResponseField('purchase', 'object', 'Short info of the purchase that sold it (`PurchaseSummaryResource`); null when it was not sold through a purchase. Present when loaded.', required: false, nullable: true)]
    public function toArray(Request $request): array
    {
        $membership = $this->resource;

        return [
            'id' => $membership->id,
            'status' => $membership->status->value,
            'service' => $this->whenLoaded('service', fn (): array => [
                'id' => $membership->service->id,
                'name' => $membership->service->getTranslations('name'),
                'color' => $membership->membership?->color,
            ]),
            'membership_id' => $membership->membership_id,
            'price' => $membership->price,
            'duration' => $membership->duration,
            'visit_service' => $this->whenLoaded('visitService', fn (): ?array => $membership->visitService === null ? null : [
                'id' => $membership->visitService->id,
                'name' => $membership->visitService->getTranslations('name'),
            ]),
            'max_visits_per_day' => $membership->max_visits_per_day,
            'visits_left_today' => $this->when(
                $this->visitsUsedToday !== null && $membership->grantsVisits(),
                fn (): int => max(0, $membership->max_visits_per_day - $this->visitsUsedToday),
            ),
            'packages' => ClientPackageResource::collection($this->whenLoaded('packages')),
            'expires_at' => $membership->expires_at?->toIso8601String(),
            'is_usable' => $membership->isUsable(),
            'created_at' => $membership->created_at?->toIso8601String(),
            'purchase' => PurchaseSummaryResource::forModel($this->resource),
        ];
    }
}

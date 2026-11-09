<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Reservations\Models\ClientMembership;

/**
 * One entry of the client's bookings feed: a stand-alone reservation, a package or a membership.
 *
 * @property array{kind: string, model: Model, visits_used_today: int|null} $resource
 */
class ClientBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[ResponseField('kind', 'string', 'What `data` is.', enum: ['reservation', 'package', 'membership'], example: 'reservation')]
    #[ResponseField('data', 'object', 'A `ReservationResource`, `ClientPackageResource` or `ClientMembershipResource` (with `visits_left_today`), by `kind`.', example: ['id' => 311, 'status' => 'confirmed', 'date' => '2026-06-05', 'start_time' => '16:00', 'total_amount' => 25500])]
    public function toArray(Request $request): array
    {
        $model = $this->resource['model'];

        return [
            'kind' => $this->resource['kind'],
            'data' => $model instanceof ClientMembership
                ? ClientMembershipResource::make($model)->withVisitsUsedToday($this->resource['visits_used_today'])
                : $this->resourceFor($this->resource['kind'], $model),
        ];
    }

    private function resourceFor(string $kind, mixed $model): JsonResource
    {
        return $kind === 'package' ? ClientPackageResource::make($model) : ReservationResource::make($model);
    }
}

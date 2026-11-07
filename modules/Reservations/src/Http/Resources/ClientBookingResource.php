<?php

namespace Modules\Reservations\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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

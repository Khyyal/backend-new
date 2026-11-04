<?php

namespace Modules\Centers\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Centers\Models\Center;
use Modules\Centers\Models\User;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Enums\ServiceType;
use Modules\Support\Models\SystemUser;

/**
 * Narrows a center's purchases to those a center user may see. A purchase is
 * visible when at least one of its items (a reservation, a client package or
 * a client membership) is for a service type the user can view and passes the trainer rule:
 * reservations of the user's own trainer are always visible, those of another
 * trainer need `purchases.view_other_trainers`, and items without a trainer
 * (including every client package and membership) need `purchases.view_unassigned`.
 *
 * The primary user and system users see every purchase.
 */
class CenterPurchaseVisibility
{
    /**
     * @param  Builder<Purchase>  $purchases
     * @return Builder<Purchase>
     */
    public function apply(Builder $purchases, User|SystemUser $user, Center $center): Builder
    {
        if ($user instanceof SystemUser || $user->isPrimaryOf($center)) {
            return $purchases;
        }

        $types = collect(ServiceType::cases())
            ->filter(fn (ServiceType $type): bool => $user->hasCenterPermission($center, CenterUserPermission::viewPurchasesFor($type)))
            ->map(fn (ServiceType $type): string => $type->value)
            ->values()
            ->all();

        $trainerId = $user->centerAssignment($center)?->trainer_id;
        $canViewOtherTrainers = $user->hasCenterPermission($center, CenterUserPermission::ViewOtherTrainersPurchases);
        $canViewUnassigned = $user->hasCenterPermission($center, CenterUserPermission::ViewUnassignedPurchases);

        return $purchases->whereHas('items', function (Builder $items) use ($types, $trainerId, $canViewOtherTrainers, $canViewUnassigned): void {
            $items->whereHasMorph(
                'purchasable',
                [Reservation::class, ClientPackage::class, ClientMembership::class],
                function (Builder $purchasable) use ($types, $trainerId, $canViewOtherTrainers, $canViewUnassigned): void {
                    $purchasable->whereHas('service', fn (Builder $service) => $service->whereIn('type', $types));

                    if (! $purchasable->getModel() instanceof Reservation) {
                        $purchasable->when(! $canViewUnassigned, fn (Builder $query) => $query->whereRaw('1 = 0'));

                        return;
                    }

                    $purchasable->where(function (Builder $trainer) use ($trainerId, $canViewOtherTrainers, $canViewUnassigned): void {
                        $trainer->whereRaw('1 = 0');

                        if ($trainerId !== null) {
                            $trainer->orWhere('trainer_id', $trainerId);
                        }

                        if ($canViewOtherTrainers) {
                            $trainer->orWhere(fn (Builder $other) => $other
                                ->whereNotNull('trainer_id')
                                ->when($trainerId !== null, fn (Builder $query) => $query->where('trainer_id', '!=', $trainerId)));
                        }

                        if ($canViewUnassigned) {
                            $trainer->orWhereNull('trainer_id');
                        }
                    });
                },
            );
        });
    }
}

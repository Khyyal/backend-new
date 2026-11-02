<?php

namespace Modules\Centers\Enums;

use Modules\Services\Enums\ServiceType;

/**
 * Permissions that can be granted to the users of a center. Stored as Spatie
 * permissions on the `center_user` guard, scoped to the center through the
 * permission team id. The primary user implicitly holds all of them.
 */
enum CenterUserPermission: string
{
    case ManageRecreationRidingServices = 'services.recreation_riding.manage';
    case ManageVisitServices = 'services.visit.manage';
    case ManageEventServices = 'services.event.manage';
    case ManageResortServices = 'services.resort.manage';
    case ManageHorseCareServices = 'services.horse_care.manage';
    case ManageHorseServiceServices = 'services.horse_service.manage';
    case ManageTrainingServices = 'services.training.manage';

    case ViewRecreationRidingPurchases = 'purchases.recreation_riding.view';
    case ViewVisitPurchases = 'purchases.visit.view';
    case ViewEventPurchases = 'purchases.event.view';
    case ViewResortPurchases = 'purchases.resort.view';
    case ViewHorseCarePurchases = 'purchases.horse_care.view';
    case ViewHorseServicePurchases = 'purchases.horse_service.view';
    case ViewTrainingPurchases = 'purchases.training.view';

    case ManageUsers = 'users.manage';

    case ViewPurchasePrices = 'purchases.view_prices';
    case EditReservations = 'reservations.edit';
    case ViewOtherTrainersPurchases = 'purchases.view_other_trainers';
    case ViewUnassignedPurchases = 'purchases.view_unassigned';

    case ManageReservations = 'reservations.manage';
    case ViewRatings = 'ratings.view';
    case ManageBlockedClients = 'blocked_clients.manage';
    case ManageDiscounts = 'discounts.manage';

    public static function manageServicesFor(ServiceType $type): self
    {
        return self::from("services.{$type->value}.manage");
    }

    public static function viewPurchasesFor(ServiceType $type): self
    {
        return self::from("purchases.{$type->value}.view");
    }

    /** @return array<self> */
    public static function manageServicesPermissions(): array
    {
        return array_map(self::manageServicesFor(...), ServiceType::cases());
    }

    /** @return array<self> */
    public static function viewPurchasesPermissions(): array
    {
        return array_map(self::viewPurchasesFor(...), ServiceType::cases());
    }

    /** @return array<string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}

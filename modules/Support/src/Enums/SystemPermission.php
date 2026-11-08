<?php

namespace Modules\Support\Enums;

enum SystemPermission: string
{
    case ViewAnySystemUsers = 'system_users.view_any';
    case ViewSystemUser = 'system_users.view';
    case CreateSystemUser = 'system_users.create';
    case UpdateSystemUser = 'system_users.update';
    case DeleteSystemUser = 'system_users.delete';
    case RestoreSystemUser = 'system_users.restore';
    case ForceDeleteSystemUser = 'system_users.force_delete';
    case AssignSystemUserRoles = 'system_users.assign_roles';

    case ViewAnySystemRoles = 'system_roles.view_any';
    case ViewSystemRole = 'system_roles.view';
    case CreateSystemRole = 'system_roles.create';
    case UpdateSystemRole = 'system_roles.update';
    case DeleteSystemRole = 'system_roles.delete';

    case ViewAnyCenters = 'centers.view_any';
    case ViewCenter = 'centers.view';
    case CreateCenter = 'centers.create';
    case ExportCenters = 'centers.export';
    case UpdateCenter = 'centers.update';
    case DeleteCenter = 'centers.delete';
    case ManageCenterServices = 'centers.manage_services';
    case ViewCenterRatings = 'centers.view_ratings';
    case ManageCenterBlockedClients = 'centers.manage_blocked_clients';
    case ManageCenterReservations = 'centers.manage_reservations';

    case ManageNotificationCampaigns = 'notification_campaigns.manage';
    case ManageWhatsAppTemplates = 'whatsapp_templates.manage';

    case ViewAnyPromotions = 'promotions.view_any';
    case ViewPromotion = 'promotions.view';
    case CreatePromotion = 'promotions.create';
    case UpdatePromotion = 'promotions.update';
    case DeletePromotion = 'promotions.delete';
    case ActivatePromotion = 'promotions.activate';
    case ManageCampaigns = 'campaigns.manage';
    case ManageCoupons = 'coupons.manage';

    case ManageHomepage = 'homepage.manage';

    case ViewAnyCities = 'cities.view_any';
    case ViewCity = 'cities.view';
    case CreateCity = 'cities.create';
    case UpdateCity = 'cities.update';
    case DeleteCity = 'cities.delete';

    case ViewAnyTags = 'tags.view_any';
    case ViewTag = 'tags.view';
    case CreateTag = 'tags.create';
    case UpdateTag = 'tags.update';
    case DeleteTag = 'tags.delete';

    case ViewAnyClients = 'clients.view_any';
    case ViewClient = 'clients.view';
    case ExportClients = 'clients.export';

    case ViewAnyPurchases = 'purchases.view_any';
    case ExportPurchases = 'purchases.export';

    case ManageFees = 'fees.manage';
    case ManagePayouts = 'payouts.manage';
    case ExportCenterLedgers = 'payouts.export';

    case ViewAnalytics = 'analytics.view';

    case ViewAnySubscriptions = 'subscriptions.view_any';
    case ChangeSubscriptions = 'subscriptions.change';

    case ViewAnyActivityLogs = 'activity_logs.view_any';

    /** @return array<string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}

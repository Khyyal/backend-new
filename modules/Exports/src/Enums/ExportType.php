<?php

namespace Modules\Exports\Enums;

/**
 * What an export contains. The export classes live in the modules that own
 * the data; this module only tracks, delivers and prunes the files.
 */
enum ExportType: string
{
    case AdminPurchases = 'admin_purchases';
    case AdminCenters = 'admin_centers';
    case AdminCenterPurchases = 'admin_center_purchases';
    case AdminClients = 'admin_clients';
    case AdminCenterLedger = 'admin_center_ledger';
    case CenterClients = 'center_clients';
    case CenterPurchases = 'center_purchases';

    /**
     * File name prefix of the downloaded spreadsheet.
     */
    public function fileNamePrefix(): string
    {
        return match ($this) {
            self::AdminPurchases, self::AdminCenterPurchases, self::CenterPurchases => 'purchases',
            self::AdminCenters => 'centers',
            self::AdminClients, self::CenterClients => 'clients',
            self::AdminCenterLedger => 'center-ledger',
        };
    }
}

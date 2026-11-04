<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Models\Feature;

/**
 * The features plans can switch on. Re-running keeps one row per key.
 */
class FeatureSeeder extends Seeder
{
    public const array FEATURES = [
        'online_bookings' => 'Online bookings',
        'services_schedules' => 'Services and schedules',
        'packages_memberships' => 'Packages and memberships',
        'promotions_coupons' => 'Promotions and coupons',
        'analytics' => 'Analytics',
        'custom_site' => 'Custom site',
    ];

    public function run(): void
    {
        foreach (self::FEATURES as $key => $name) {
            Feature::query()->updateOrCreate(['key' => $key], ['name' => $name, 'is_quota' => false]);
        }
    }
}

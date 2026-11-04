<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Models\Limit;

/**
 * The quotas plans cap. Re-running keeps one row per key.
 */
class LimitSeeder extends Seeder
{
    public const array LIMITS = [
        'services' => 'Services',
        'trainers' => 'Trainers',
        'staff_users' => 'Staff users',
        'monthly_reservations' => 'Reservations per month',
    ];

    public function run(): void
    {
        foreach (self::LIMITS as $key => $name) {
            Limit::query()->updateOrCreate(['key' => $key], ['name' => $name]);
        }
    }
}

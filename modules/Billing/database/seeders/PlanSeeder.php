<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Models\Feature;
use Modules\Billing\Models\Limit;
use Modules\Billing\Models\Plan;

/**
 * The plans centers can subscribe to, with the features each one switches on
 * and its limits. Prices are in halalas. Re-running keeps one row per slug and
 * updates the attachments it seeds without dropping others.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FeatureSeeder::class,
            LimitSeeder::class,
        ]);

        $plans = [
            [
                'slug' => 'trial',
                'features' => ['online_bookings', 'services_schedules'],
                'limits' => ['services' => 3, 'trainers' => 2, 'staff_users' => 2, 'monthly_reservations' => 50],
                'name' => ['en' => 'Trial', 'ar' => 'تجريبية'],
                'description' => [
                    'en' => 'Try the platform free for 14 days.',
                    'ar' => 'جرّب المنصة مجاناً لمدة 14 يوماً.',
                ],
                'price' => 0,
                'trial_days' => 14,
                'display_features' => [
                    'en' => ['All basic features', 'No payment required'],
                    'ar' => ['جميع المزايا الأساسية', 'بدون دفع'],
                ],
            ],
            [
                'slug' => 'basic',
                'features' => ['online_bookings', 'services_schedules'],
                'limits' => ['services' => 10, 'trainers' => 5, 'staff_users' => 3, 'monthly_reservations' => 300],
                'name' => ['en' => 'Basic', 'ar' => 'الأساسية'],
                'description' => [
                    'en' => 'Everything a small center needs to take bookings.',
                    'ar' => 'كل ما يحتاجه المركز الصغير لاستقبال الحجوزات.',
                ],
                'price' => 19900,
                'trial_days' => 0,
                'display_features' => [
                    'en' => ['Online bookings', 'Services and schedules'],
                    'ar' => ['الحجز الإلكتروني', 'الخدمات والجداول'],
                ],
            ],
            [
                'slug' => 'advanced',
                'features' => ['online_bookings', 'services_schedules', 'packages_memberships', 'promotions_coupons'],
                'limits' => ['services' => 30, 'trainers' => 15, 'staff_users' => 10, 'monthly_reservations' => 1500],
                'name' => ['en' => 'Advanced', 'ar' => 'المتقدمة'],
                'description' => [
                    'en' => 'For growing centers with packages and promotions.',
                    'ar' => 'للمراكز المتنامية مع الباقات والعروض.',
                ],
                'price' => 39900,
                'trial_days' => 0,
                'display_features' => [
                    'en' => ['Everything in Basic', 'Packages and memberships', 'Promotions and coupons'],
                    'ar' => ['كل مزايا الأساسية', 'الباقات والعضويات', 'العروض والقسائم'],
                ],
            ],
            [
                'slug' => 'professional',
                'features' => array_keys(FeatureSeeder::FEATURES),
                'limits' => ['services' => 100, 'trainers' => 50, 'staff_users' => 30, 'monthly_reservations' => 10000],
                'name' => ['en' => 'Professional', 'ar' => 'الاحترافية'],
                'description' => [
                    'en' => 'For established centers that need the full toolkit.',
                    'ar' => 'للمراكز الراسخة التي تحتاج جميع الأدوات.',
                ],
                'price' => 79900,
                'trial_days' => 0,
                'display_features' => [
                    'en' => ['Everything in Advanced', 'Analytics', 'Custom site'],
                    'ar' => ['كل مزايا المتقدمة', 'التحليلات', 'موقع مخصص'],
                ],
            ],
        ];

        $features = Feature::query()->whereIn('key', array_keys(FeatureSeeder::FEATURES))->pluck('id', 'key');
        $limits = Limit::query()->whereIn('key', array_keys(LimitSeeder::LIMITS))->pluck('id', 'key');

        foreach ($plans as $attributes) {
            $enabledFeatures = $attributes['features'];
            $limitValues = $attributes['limits'];
            unset($attributes['features'], $attributes['limits']);

            $plan = Plan::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [
                    ...$attributes,
                    'billing_interval' => BillingInterval::Monthly,
                    'status' => PlanStatus::Active,
                ],
            );

            $plan->features()->syncWithoutDetaching(
                $features->mapWithKeys(fn (int $id, string $key): array => [
                    $id => ['enabled' => in_array($key, $enabledFeatures, true)],
                ])->all()
            );

            $plan->limits()->syncWithoutDetaching(
                collect($limitValues)->mapWithKeys(fn (int $value, string $key): array => [
                    $limits[$key] => ['value' => $value],
                ])->all()
            );
        }
    }
}

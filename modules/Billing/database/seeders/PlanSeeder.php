<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Models\Plan;

/**
 * The plans centers can subscribe to. Prices are in halalas. Re-running keeps
 * one row per slug.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'trial',
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

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    ...$plan,
                    'billing_interval' => BillingInterval::Monthly,
                    'status' => PlanStatus::Active,
                ],
            );
        }
    }
}

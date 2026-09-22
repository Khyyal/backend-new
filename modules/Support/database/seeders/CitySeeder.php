<?php

namespace Modules\Support\Database\Seeders;

use Modules\Support\Models\City;

class CitySeeder extends DatabaseSeeder
{
    public function run(): void
    {
        $cities = [
            [
                'id' => 21,
                'name' => [
                    'ar' => 'الرياض',
                    'en' => 'Riyadh',
                ],
                'lat' => 24.77426500,
                'lng' => 46.73858600,
                'radius' => 150,
            ],
            [
                'id' => 22,
                'name' => [
                    'ar' => 'جدة',
                    'en' => 'Jeddah',
                ],
                'lat' => 21.49250000,
                'lng' => 39.17757000,
                'radius' => 80,
            ],
            [
                'id' => 23,
                'name' => [
                    'ar' => 'مكة',
                    'en' => 'Makkah',
                ],
                'lat' => 21.42251000,
                'lng' => 39.82616800,
                'radius' => 60,
            ],
            [
                'id' => 24,
                'name' => [
                    'ar' => 'المدينة',
                    'en' => 'Madinah',
                ],
                'lat' => 24.47090100,
                'lng' => 39.61223600,
                'radius' => 50,
            ],
            [
                'id' => 26,
                'name' => [
                    'ar' => 'الخبر',
                    'en' => 'Khobar',
                ],
                'lat' => 26.23635500,
                'lng' => 50.03260000,
                'radius' => 40,
            ],
            [
                'id' => 27,
                'name' => [
                    'ar' => 'الطائف',
                    'en' => 'Taif',
                ],
                'lat' => 21.43727300,
                'lng' => 40.51271400,
                'radius' => 45,
            ],
            [
                'id' => 30,
                'name' => [
                    'ar' => 'القصيم',
                    'en' => 'Qassim',
                ],
                'lat' => 26.33333300,
                'lng' => 43.96666700,
                'radius' => 70,
            ],
            [
                'id' => 31,
                'name' => [
                    'ar' => 'حائل',
                    'en' => 'Hail',
                ],
                'lat' => 27.52364700,
                'lng' => 41.69663200,
                'radius' => 50,
            ],

            [
                'id' => 33,
                'name' => [
                    'ar' => 'نجران',
                    'en' => 'Najran',
                ],
                'lat' => 17.50000000,
                'lng' => 42.50000000,
                'radius' => 50,
            ],
            [
                'id' => 34,
                'name' => [
                    'ar' => 'جيزان',
                    'en' => 'Jazan',
                ],
                'lat' => 16.88916700,
                'lng' => 42.56111100,
                'radius' => 45,
            ],
            [
                'id' => 35,
                'name' => [
                    'ar' => 'عسير',
                    'en' => 'Asir',
                ],
                'lat' => 18.32938400,
                'lng' => 42.75936500,
                'radius' => 80,
            ],
            [
                'id' => 36,
                'name' => [
                    'ar' => 'الباحة',
                    'en' => 'Al-Baha',
                ],
                'lat' => 20.01666700,
                'lng' => 41.46666700,
                'radius' => 40,
            ],
            [
                'id' => 37,
                'name' => [
                    'ar' => 'تبوك',
                    'en' => 'Tabuk',
                ],
                'lat' => 28.39980000,
                'lng' => 36.57150000,
                'radius' => 70,
            ],
            [
                'id' => 38,
                'name' => [
                    'ar' => 'الحدود الشمالية',
                    'en' => 'Northern Borders',
                ],
                'lat' => 30.98333400,
                'lng' => 41.01666600,
                'radius' => 90,
            ],
            [
                'id' => 39,
                'name' => [
                    'ar' => 'الجوف',
                    'en' => 'Al-Jawf',
                ],
                'lat' => 29.88736000,
                'lng' => 39.32062000,
                'radius' => 70,
            ],
            [
                'id' => 40,
                'name' => [
                    'ar' => 'الشرقية',
                    'en' => 'Eastern Province',
                ],
                'lat' => 26.42820000,
                'lng' => 50.09970000,
                'radius' => 120,
            ],
            [
                'id' => 41,
                'name' => [
                    'ar' => 'الأحساء',
                    'en' => 'Al-Ahsa',
                ],
                'lat' => 25.25000000,
                'lng' => 49.61670000,
                'radius' => 70,
            ],
            [
                'id' => 42,
                'name' => [
                    'ar' => 'حفر الباطن',
                    'en' => 'Hafar Al Batin',
                ],
                'lat' => 28.44695900,
                'lng' => 45.94894400,
                'radius' => 60,
            ],
            [
                'id' => 43,
                'name' => [
                    'ar' => 'العلا',
                    'en' => 'Al Ula',
                ],
                'lat' => 26.60800000,
                'lng' => 37.92300000,
                'radius' => 50,
            ],
            [
                'id' => 44,
                'name' => [
                    'ar' => 'المجمعة',
                    'en' => "Al Majma'ah",
                ],
                'lat' => 25.90388900,
                'lng' => 45.34555600,
                'radius' => 40,
            ],
            [
                'id' => 45,
                'name' => [
                    'ar' => 'ينبع',
                    'en' => 'Yanbu',
                ],
                'lat' => 24.08957600,
                'lng' => 38.06205000,
                'radius' => 50,
            ],
        ];

        $cities = array_map(
            fn (array $city) => [
                ...$city,
                'name' => json_encode(
                    $city['name'],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
            ],
            $cities
        );

        City::insert($cities);
    }
}

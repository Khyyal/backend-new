<?php

namespace Modules\Centers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centers\Models\Tag;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            [
                'id' => 1,
                'name' => [
                    'ar' => 'مركز فروسي',
                    'en' => 'Center',
                ],
            ],
            [
                'id' => 2,
                'name' => [
                    'ar' => 'نسائي',
                    'en' => 'Ladies',
                ],
            ],
            [
                'id' => 3,
                'name' => [
                    'ar' => 'أيام مخصصة للنساء',
                    'en' => 'Ladies specified days',
                ],
            ],
            [
                'id' => 4,
                'name' => [
                    'ar' => 'قفز حواجز',
                    'en' => 'Show jumping',
                ],

            ],
            [
                'id' => 5,
                'name' => [
                    'ar' => 'جولات ركوب خارجية',
                    'en' => 'Outside free rides',
                ],

            ],
            [
                'id' => 6,
                'name' => [
                    'ar' => 'حصص للأطفال',
                    'en' => 'Kids classes',
                ],

            ],
            [
                'id' => 7,
                'name' => [
                    'ar' => 'ملاعب تنس وبادل',
                    'en' => 'Tennis & Padel courts',
                ],
            ],
        ];

        foreach ($tags as &$tag) {
            $tag['name'] = json_encode(
                $tag['name'],
                JSON_UNESCAPED_UNICODE
            );
        }

        Tag::upsert(
            $tags,
            ['id'],
            ['name']
        );
    }
}

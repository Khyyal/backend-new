<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Models\Tag;

require_once __DIR__.'/LegacySchema.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    useLegacyServiceSchema();

    CenterFactory::new()->create(['id' => 6]);

    DB::connection('legacy')->table('features')->insert([
        ['id' => 1, 'name' => legacyName('مركز فروسي', 'Center')],
        ['id' => 2, 'name' => legacyName('نسائي', 'Ladies')],
    ]);

    DB::connection('legacy')->table('barn_features')->insert([
        ['id' => 1, 'barn_id' => 6, 'feature_id' => 1],
        ['id' => 2, 'barn_id' => 6, 'feature_id' => 2],
        ['id' => 3, 'barn_id' => 999, 'feature_id' => 1],
    ]);
});

test('barn features become tags with their ids and translated names', function (): void {
    $this->artisan('migrator:tags')->assertSuccessful();

    expect(Tag::query()->findOrFail(2)->getTranslation('name', 'en'))->toBe('Ladies')
        ->and(Tag::query()->findOrFail(1)->getTranslation('name', 'ar'))->toBe('مركز فروسي');
});

test('tags are linked only to migrated centers and re-running adds nothing', function (): void {
    $this->artisan('migrator:tags')->assertSuccessful();
    $this->artisan('migrator:tags')->assertSuccessful();

    expect(DB::table('tag_center')->orderBy('tag_id')->get(['tag_id', 'center_id'])->map(fn ($row): array => (array) $row)->all())
        ->toBe([
            ['tag_id' => 1, 'center_id' => 6],
            ['tag_id' => 2, 'center_id' => 6],
        ])
        ->and(DB::table('tags')->count())->toBe(2);
});

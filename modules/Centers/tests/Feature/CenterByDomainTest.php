<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('returns the center id, slug and site theme for its custom domain', function (): void {
    $center = CenterFactory::new()->withCustomSite()->create(['status' => 'visible', 'domain' => 'alfursan.sa']);

    $this->getJson('/api/v1/clients/centers/by-domain/ALFURSAN.sa')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $center->id,
            'slug' => $center->slug,
            'site_theme' => $center->site_theme,
        ]]);
});

test('returns 404 when the center cannot be served on the domain', function (array $attributes): void {
    CenterFactory::new()->withCustomSite()->create(['domain' => 'alfursan.sa', ...$attributes]);

    $this->getJson('/api/v1/clients/centers/by-domain/alfursan.sa')->assertNotFound();
})->with([
    'custom site disabled' => [['status' => 'visible', 'custom_site_enabled' => false]],
    'invisible' => [['status' => 'invisible']],
    'not active' => [['status' => 'visible', 'state' => 'blocked']],
]);

test('returns 404 for an unknown domain', function (): void {
    $this->getJson('/api/v1/clients/centers/by-domain/unknown.sa')->assertNotFound();
});

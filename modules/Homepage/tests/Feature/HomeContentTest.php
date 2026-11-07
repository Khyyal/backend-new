<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Homepage\Database\Factories\BannerFactory;
use Modules\Homepage\Database\Factories\HomeSectionFactory;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Enums\ItemableType;
use Modules\Homepage\Models\HomeSection;
use Modules\Promotions\Database\Factories\CampaignFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Enums\CampaignStatus;
use Modules\Services\Database\Factories\ServiceFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const RIYADH = ['lat' => 24.7136, 'lng' => 46.6753];
const JEDDAH = ['lat' => 21.5433, 'lng' => 39.1728];

beforeEach(function (): void {
    $this->pick = function (HomeSection $section, array $records, ItemableType $type, array $window = []): void {
        foreach (array_values($records) as $position => $record) {
            $section->items()->create(['itemable_type' => $type, 'itemable_id' => $record->id, 'sort_order' => $position, ...$window]);
        }
    };
});

test('shows only published sections inside their schedule, in order', function (): void {
    $center = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);
    $second = HomeSectionFactory::new()->published()->create(['key' => 'second', 'sort_order' => 2]);
    $first = HomeSectionFactory::new()->published()->create(['key' => 'first', 'sort_order' => 1]);
    $draft = HomeSectionFactory::new()->create(['key' => 'draft']);
    $ended = HomeSectionFactory::new()->published()->create(['key' => 'ended', 'ends_at' => now()->subMinute()]);

    foreach ([$first, $second, $draft, $ended] as $section) {
        ($this->pick)($section, [$center], ItemableType::Center);
    }

    $this->getJson('/api/v1/clients/home')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'first')
        ->assertJsonPath('data.1.key', 'second')
        ->assertJsonPath('data.0.items.0.id', $center->id);
});

test('curated centers keep the picked order and skip hidden centers and ended items', function (): void {
    $section = HomeSectionFactory::new()->published()->create(['key' => 'picks']);
    [$a, $b] = CenterFactory::new()->count(2)->create(['status' => CenterStatus::VISIBLE]);
    $hidden = CenterFactory::new()->create(['status' => CenterStatus::INVISIBLE]);
    $expired = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);

    ($this->pick)($section, [$b, $hidden, $a], ItemableType::Center);
    $section->items()->create(['itemable_type' => ItemableType::Center, 'itemable_id' => $expired->id, 'sort_order' => 9, 'ends_at' => now()->subHour()]);

    $this->getJson('/api/v1/clients/home')
        ->assertJsonCount(2, 'data.0.items')
        ->assertJsonPath('data.0.items.0.id', $b->id)
        ->assertJsonPath('data.0.items.1.id', $a->id)
        ->assertJsonPath('data.0.has_more', false);
});

test('drops sections with nothing to show', function (): void {
    $section = HomeSectionFactory::new()->published()->create();
    ($this->pick)($section, [CenterFactory::new()->create(['status' => CenterStatus::INVISIBLE])], ItemableType::Center);

    $this->getJson('/api/v1/clients/home')->assertOk()->assertJsonCount(0, 'data');
});

test('the radius hides centers far from the client', function (): void {
    $section = HomeSectionFactory::new()->published()->create(['config' => ['limit' => 10, 'radius_km' => 50]]);
    $near = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE, ...RIYADH]);
    $far = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE, ...JEDDAH]);
    ($this->pick)($section, [$near, $far], ItemableType::Center);

    $this->getJson('/api/v1/clients/home?'.http_build_query(RIYADH))
        ->assertJsonCount(1, 'data.0.items')
        ->assertJsonPath('data.0.items.0.id', $near->id);
});

test('curated services show active services of visible centers', function (): void {
    $section = HomeSectionFactory::new()->ofType(HomeSectionType::CuratedServices)->published()->create();
    $center = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);
    $active = ServiceFactory::new()->create(['center_id' => $center->id]);
    $active->priceOptions()->create(['price' => 5000]);
    $inactive = ServiceFactory::new()->inactive()->create(['center_id' => $center->id]);
    $ofHiddenCenter = ServiceFactory::new()->create(['center_id' => CenterFactory::new()->create(['status' => CenterStatus::INVISIBLE])->id]);
    ($this->pick)($section, [$inactive, $active, $ofHiddenCenter], ItemableType::Service);

    $this->getJson('/api/v1/clients/home')
        ->assertJsonCount(1, 'data.0.items')
        ->assertJsonPath('data.0.items.0.id', $active->id)
        ->assertJsonPath('data.0.items.0.center.id', $center->id)
        ->assertJsonPath('data.0.items.0.start_price', 5000);
});

test('curated promotions and campaigns show only running ones', function (): void {
    $offers = HomeSectionFactory::new()->ofType(HomeSectionType::CuratedPromotions)->published()->create(['key' => 'offers', 'sort_order' => 1]);
    $running = PromotionFactory::new()->active()->create();
    ($this->pick)($offers, [PromotionFactory::new()->create(), $running], ItemableType::Promotion);

    $campaigns = HomeSectionFactory::new()->ofType(HomeSectionType::CuratedCampaigns)->published()->create(['key' => 'campaigns', 'sort_order' => 2]);
    $live = CampaignFactory::new()->create(['status' => CampaignStatus::Active]);
    $over = CampaignFactory::new()->create(['status' => CampaignStatus::Active, 'ends_at' => now()->subDay()]);
    ($this->pick)($campaigns, [$over, $live], ItemableType::Campaign);

    $this->getJson('/api/v1/clients/home')
        ->assertJsonCount(1, 'data.0.items')
        ->assertJsonPath('data.0.items.0.id', $running->id)
        ->assertJsonCount(1, 'data.1.items')
        ->assertJsonPath('data.1.items.0.id', $live->id);
});

test('top centers are nearest first with a position and most popular first without', function (): void {
    HomeSectionFactory::new()->ofType(HomeSectionType::TopCenters)->published()->create();
    $popularFar = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE, 'points' => 100, ...JEDDAH]);
    $quietNear = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE, 'points' => 1, ...RIYADH]);

    $this->getJson('/api/v1/clients/home')
        ->assertJsonPath('data.0.items.0.id', $popularFar->id);

    $this->getJson('/api/v1/clients/home?'.http_build_query(RIYADH))
        ->assertJsonPath('data.0.items.0.id', $quietNear->id);
});

test('banner carousels show live banners in the request locale', function (): void {
    $carousel = HomeSectionFactory::new()->ofType(HomeSectionType::BannerCarousel)->published()->create();
    $live = BannerFactory::new()->published()->create(['home_section_id' => $carousel->id, 'title' => ['en' => 'Ride', 'ar' => 'اركب']]);
    BannerFactory::new()->create(['home_section_id' => $carousel->id]);
    BannerFactory::new()->published()->create(['home_section_id' => $carousel->id, 'starts_at' => now()->addDay()]);
    BannerFactory::new()->published()->create([
        'home_section_id' => $carousel->id,
        'center_id' => CenterFactory::new()->create(['status' => CenterStatus::INVISIBLE])->id,
    ]);

    $this->getJson('/api/v1/clients/home', ['Accept-Language' => 'ar'])
        ->assertJsonCount(1, 'data.0.items')
        ->assertJsonPath('data.0.items.0.id', $live->id)
        ->assertJsonPath('data.0.items.0.title', 'اركب')
        ->assertJsonPath('data.0.items.0.action', ['type' => 'none']);
});

test('pages a section beyond its home page limit', function (): void {
    $section = HomeSectionFactory::new()->published()->create(['key' => 'picks', 'config' => ['limit' => 2]]);
    ($this->pick)($section, CenterFactory::new()->count(3)->create(['status' => CenterStatus::VISIBLE])->all(), ItemableType::Center);

    $this->getJson('/api/v1/clients/home')
        ->assertJsonCount(2, 'data.0.items')
        ->assertJsonPath('data.0.has_more', true);

    $this->getJson('/api/v1/clients/home/sections/picks?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.has_more', false)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2);
});

test('returns 404 for a section that is not visible', function (): void {
    HomeSectionFactory::new()->create(['key' => 'draft']);

    $this->getJson('/api/v1/clients/home/sections/draft')->assertNotFound();
    $this->getJson('/api/v1/clients/home/sections/missing')->assertNotFound();
});

test('editing a section shows on the next request', function (): void {
    $section = HomeSectionFactory::new()->published()->create();
    $center = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);
    ($this->pick)($section, [$center], ItemableType::Center);

    $this->getJson('/api/v1/clients/home')->assertJsonCount(1, 'data');

    $section->update(['status' => 'archived']);

    $this->getJson('/api/v1/clients/home')->assertJsonCount(0, 'data');
});

test('lat and lng must be sent together', function (): void {
    $this->getJson('/api/v1/clients/home?lat=24.7')->assertJsonValidationErrors('lng');
});

test('home definitions survive a serializing cache store', function (): void {
    config(['cache.default' => 'file', 'cache.stores.file.path' => storage_path('framework/testing/cache-'.uniqid())]);
    Cache::purge('file');
    Cache::flush();

    HomeSectionFactory::new()->published()->create(['key' => 'picks']);

    $this->getJson('/api/v1/clients/home')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/clients/home')->assertOk();

    Cache::flush();
});

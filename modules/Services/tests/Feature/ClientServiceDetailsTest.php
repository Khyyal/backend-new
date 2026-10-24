<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Services\Database\Factories\EventFactory;
use Modules\Services\Database\Factories\HorseCareFactory;
use Modules\Services\Database\Factories\HorseServiceFactory;
use Modules\Services\Database\Factories\RecreationalRidingFactory;
use Modules\Services\Database\Factories\ResortFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Database\Factories\TrainingServiceFactory;
use Modules\Services\Database\Factories\VisitFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);
    $this->url = fn (Service $service, ?int $center = null) => '/api/v1/clients/centers/'
        .($center ?? $service->center_id)."/services/{$service->slug}";
});

test('returns the serviceable details of each service type', function (ServiceType $type, string $factory, string $typeSpecificKey): void {
    $service = ServiceFactory::new()
        ->forServiceable($factory::new()->create(), $type)
        ->create(['center_id' => $this->center->id]);

    $this->getJson(($this->url)($service))
        ->assertOk()
        ->assertJsonPath('data.type', $type->value)
        ->assertJsonPath('data.details.id', $service->serviceable_id)
        ->assertJsonPath('data.details.service_id', $service->id)
        ->assertJsonPath('data.details.slug', $service->slug)
        ->assertJsonStructure(['data' => ['details' => ['name', 'description', 'cover', 'images', $typeSpecificKey]]]);
})->with([
    'recreation riding' => [ServiceType::RecreationRiding, RecreationalRidingFactory::class, 'max_tickets_per_hour'],
    'visit' => [ServiceType::Visit, VisitFactory::class, 'enter_type'],
    'event' => [ServiceType::Event, EventFactory::class, 'settings'],
    'resort' => [ServiceType::Resort, ResortFactory::class, 'days'],
    'horse care' => [ServiceType::HorseCare, HorseCareFactory::class, 'price_options'],
    'horse service' => [ServiceType::HorseService, HorseServiceFactory::class, 'price_options'],
    'training' => [ServiceType::Training, TrainingServiceFactory::class, 'trainers'],
]);

test('resolves the slug within the given center', function (): void {
    $other = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE]);
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id, 'name' => ['ar' => 'خدمة', 'en' => 'Same']]);
    $twin = ServiceFactory::new()->create(['center_id' => $other->id, 'name' => ['ar' => 'خدمة', 'en' => 'Same']]);

    expect($twin->slug)->toBe($service->slug);

    $this->getJson(($this->url)($twin))
        ->assertOk()
        ->assertJsonPath('data.details.service_id', $twin->id);
});

test('returns 404 for a service that cannot be shown', function (string $case): void {
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    $center = null;

    match ($case) {
        'unknown slug' => $service->slug = 'missing',
        'slug of another center' => $center = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE])->id,
        'inactive service' => $service->update(['status' => ActivationStatus::INACTIVE]),
        'invisible center' => $service->center->update(['status' => CenterStatus::INVISIBLE]),
        'deleted service' => $service->delete(),
    };

    $this->getJson(($this->url)($service, $center))->assertNotFound();
})->with(['unknown slug', 'slug of another center', 'inactive service', 'invisible center', 'deleted service']);

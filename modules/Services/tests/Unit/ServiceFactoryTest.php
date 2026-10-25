<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServicePackage;
use Modules\Services\Models\TrainingService;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('service factory builds a valid active training service', function (): void {
    $service = Service::factory()->create();

    expect($service->status)->toBe(ActivationStatus::ACTIVE)
        ->and($service->type)->toBe(ServiceType::Training)
        ->and($service->slug)->not->toBeEmpty()
        ->and($service->serviceable)->toBeInstanceOf(TrainingService::class)
        ->and($service->center)->not->toBeNull();
});

test('service factory supports inactive and explicit serviceable states', function (): void {
    $training = TrainingService::query()->create();

    $service = Service::factory()->inactive()->forServiceable($training, ServiceType::Training)->create();

    expect($service->status)->toBe(ActivationStatus::INACTIVE)
        ->and($service->serviceable->is($training))->toBeTrue();
});

test('price option and package factories chain from the service factory', function (): void {
    $package = ServicePackage::factory()->create();

    expect($package->priceOption)->toBeInstanceOf(PriceOption::class)
        ->and($package->service_id)->toBe($package->priceOption->service_id);
});

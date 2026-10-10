<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Services\Database\Factories\TrainingServiceFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Schedule;
use Modules\Services\Models\Trainer;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('trainer belongs to a center', function (): void {
    $center = CenterFactory::new()->create();

    $trainer = TrainerFactory::new()->create(['center_id' => $center->id]);

    expect($trainer->center->is($center))->toBeTrue();
});

test('trainers and training services are linked many to many', function (): void {
    $trainingServices = TrainingServiceFactory::new()->count(2)->create();
    $trainers = TrainerFactory::new()->count(2)->create();

    $trainingServices[0]->trainers()->attach($trainers->pluck('id'));
    $trainers[0]->trainingServices()->attach($trainingServices[1]);

    expect($trainingServices[0]->trainers)->toHaveCount(2)
        ->and($trainingServices[1]->trainers->pluck('id')->all())->toBe([$trainers[0]->id])
        ->and($trainers[0]->trainingServices)->toHaveCount(2)
        ->and($trainers[1]->trainingServices)->toHaveCount(1);
});

test('trainer has schedules', function (): void {
    $trainer = TrainerFactory::new()->create();

    $trainer->schedules()->createMany([
        ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '10:00'],
        ['day_of_week' => 2, 'start_time' => '14:00', 'end_time' => '15:00'],
    ]);

    expect($trainer->schedules)->toHaveCount(2)
        ->and(Schedule::first()->schedulable)->toBeInstanceOf(Trainer::class);
});

test('training service is a serviceable of type training', function (): void {
    $center = CenterFactory::new()->create();
    $trainingService = TrainingServiceFactory::new()->create();

    $trainingService->service()->create([
        'center_id' => $center->id,
        'name' => ['ar' => 'تدريب', 'en' => 'Training'],
        'description' => ['ar' => 'وصف', 'en' => 'Description'],
        'status' => ActivationStatus::ACTIVE,
        'type' => ServiceType::Training,
    ]);

    expect($trainingService->service->type)->toBe(ServiceType::Training)
        ->and($trainingService->service->serviceable->is($trainingService))->toBeTrue();
});

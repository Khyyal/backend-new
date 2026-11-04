<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Services\Database\Factories\RecreationalRidingFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Database\Factories\ServicePackageFactory;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Services\Database\Factories\TrainingServiceFactory;
use Modules\Services\Database\Factories\VisitFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Membership;
use Modules\Services\Models\MembershipPackage;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServicePackage;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A package on a fresh price option of the given service.
 */
function membershipTestPackage(Service $service): ServicePackage
{
    $option = PriceOption::factory()->create(['service_id' => $service->id]);

    return ServicePackageFactory::new()->create(['price_option_id' => $option->id, 'service_id' => $service->id]);
}

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageMembershipServices]);
    $this->url = "/api/v1/centers/{$this->center->id}/memberships";

    $this->training = TrainingServiceFactory::new()->create();
    $this->trainingService = ServiceFactory::new()
        ->forServiceable($this->training, ServiceType::Training)
        ->create(['center_id' => $this->center->id]);
    $this->trainingPackage = membershipTestPackage($this->trainingService);

    $this->ridingService = ServiceFactory::new()
        ->forServiceable(RecreationalRidingFactory::new()->create(), ServiceType::RecreationRiding)
        ->create(['center_id' => $this->center->id]);
    $this->ridingPackage = membershipTestPackage($this->ridingService);

    $this->visitService = ServiceFactory::new()
        ->forServiceable(VisitFactory::new()->create(), ServiceType::Visit)
        ->create(['center_id' => $this->center->id]);

    $this->body = fn (array $extra = []) => [
        'name' => ['ar' => 'عضوية', 'en' => 'Membership'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'price' => 50000,
        'duration' => 3,
        'color' => '#1A2B3C',
        'packages' => [
            ['service_package_id' => $this->trainingPackage->id],
            ['service_package_id' => $this->ridingPackage->id],
        ],
        ...$extra,
    ];
});

test('guests and users without the permission are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageTrainingServices]);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)())->assertForbidden();
});

test('creates a membership with its packages, price and visit perk', function (): void {
    $trainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    $this->training->trainers()->attach($trainer);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([
            'packages' => [
                ['service_package_id' => $this->trainingPackage->id, 'trainer_id' => $trainer->id],
                ['service_package_id' => $this->ridingPackage->id],
            ],
            'visit_service_id' => $this->visitService->id,
            'max_visits_per_day' => 2,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.name.ar', 'عضوية')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.price', 50000)
        ->assertJsonPath('data.duration', 3)
        ->assertJsonPath('data.color', '#1A2B3C')
        ->assertJsonPath('data.visit_service.id', $this->visitService->id)
        ->assertJsonPath('data.max_visits_per_day', 2)
        ->assertJsonCount(2, 'data.packages')
        ->assertJsonPath('data.packages.0.service_package_id', $this->trainingPackage->id)
        ->assertJsonPath('data.packages.0.price_option_id', $this->trainingPackage->price_option_id)
        ->assertJsonPath('data.packages.0.trainer.id', $trainer->id)
        ->assertJsonPath('data.packages.1.trainer', null);

    $membership = Membership::sole();

    expect($membership->service->type)->toBe(ServiceType::Membership)
        ->and($membership->service->priceOptions)->toHaveCount(1)
        ->and(MembershipPackage::count())->toBe(2);
});

test('validates the body', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([
            'price' => -1,
            'duration' => 0,
            'color' => 'red',
            'packages' => [],
            'visit_service_id' => $this->visitService->id,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price', 'duration', 'color', 'packages', 'max_visits_per_day']);
});

test('rejects a visit service that is not a visit of this center', function (): void {
    $foreignVisit = ServiceFactory::new()
        ->forServiceable(VisitFactory::new()->create(), ServiceType::Visit)
        ->create();

    $this->actingAs($this->user, 'center_user');

    $this->postJson($this->url, ($this->body)(['visit_service_id' => $foreignVisit->id, 'max_visits_per_day' => 1]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['visit_service_id']);

    $this->postJson($this->url, ($this->body)(['visit_service_id' => $this->trainingService->id, 'max_visits_per_day' => 1]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['visit_service_id']);
});

test('rejects packages of another center and invalid trainers', function (): void {
    $foreignPackage = membershipTestPackage(ServiceFactory::new()->create());
    $otherTrainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([
            'packages' => [
                ['service_package_id' => $foreignPackage->id],
                ['service_package_id' => $this->trainingPackage->id, 'trainer_id' => $otherTrainer->id],
                ['service_package_id' => $this->ridingPackage->id, 'trainer_id' => $otherTrainer->id],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['packages.0.service_package_id', 'packages.1.trainer_id', 'packages.2.trainer_id']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->body)([
        'price' => 70000,
        'packages' => [['service_package_id' => $this->ridingPackage->id]],
    ]))->assertOk()
        ->assertJsonPath('data.price', 70000)
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath('data.packages.0.service_package_id', $this->ridingPackage->id);

    expect(PriceOption::query()->where('service_id', Membership::find($id)->service->id)->count())->toBe(1);

    $this->deleteJson("{$this->url}/{$id}")->assertNoContent();
    $this->getJson("{$this->url}/{$id}")->assertNotFound();

    expect(Membership::count())->toBe(0)
        ->and(ServicePackage::count())->toBe(2);
});

test('flags packages deleted after the membership was created', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->trainingPackage->delete();

    $this->getJson("{$this->url}/{$id}")
        ->assertOk()
        ->assertJsonPath('data.packages.0.available', false)
        ->assertJsonPath('data.packages.1.available', true);
});

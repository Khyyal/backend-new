<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Support\Database\Seeders\SuperAdminSeeder;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it seeds an active super admin that can log in', function (): void {
    config(['support.super_admin' => ['name' => 'Root', 'email' => 'root@khyyal.test', 'password' => 'secret-pass-123']]);

    $this->seed(SystemRolePermissionSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    $this->postJson('/api/v1/admin/auth/login', ['email' => 'root@khyyal.test', 'password' => 'secret-pass-123'])
        ->assertOk()
        ->assertJsonPath('user.roles', ['super_admin'])
        ->assertJsonPath('user.name', 'Root');
});

test('running it twice keeps one user and resets the password', function (): void {
    config(['support.super_admin' => ['name' => 'Root', 'email' => 'root@khyyal.test', 'password' => 'first-pass-123']]);
    $this->seed(SuperAdminSeeder::class);

    config(['support.super_admin.password' => 'second-pass-123']);
    $this->seed(SuperAdminSeeder::class);

    expect(SystemUser::query()->where('email', 'root@khyyal.test')->count())->toBe(1);
    $this->postJson('/api/v1/admin/auth/login', ['email' => 'root@khyyal.test', 'password' => 'second-pass-123'])->assertOk();
});

test('it seeds nothing without credentials', function (): void {
    config(['support.super_admin' => ['name' => 'Root', 'email' => null, 'password' => null]]);

    $this->seed(SuperAdminSeeder::class);

    expect(SystemUser::query()->count())->toBe(0);
});

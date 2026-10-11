<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Models\User;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('system user can log in and receives a token with roles and permissions', function () {
    $this->seed(SystemRolePermissionSeeder::class);
    $user = SystemUser::factory()->superAdmin()->create(['email' => 'admin@khyyal.test']);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@khyyal.test',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.roles', ['super_admin']);

    expect($response->json('token'))->toBeString()
        ->and($response->json('user.permissions'))->toContain('system_users.view_any');
});

test('login fails with wrong password', function () {
    SystemUser::factory()->create(['email' => 'admin@khyyal.test']);

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@khyyal.test',
        'password' => 'wrong',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('login fails for an unknown email', function () {
    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'nobody@khyyal.test',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('login fails for an inactive system user', function () {
    SystemUser::factory()->inactive()->create(['email' => 'admin@khyyal.test']);

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@khyyal.test',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('login requires email and password', function () {
    $this->postJson('/api/v1/admin/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

test('me returns the authenticated system user', function () {
    $user = SystemUser::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('me requires authentication', function () {
    $this->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
});

test('a center user token cannot access me', function () {
    $centerUser = User::factory()->create();
    $token = $centerUser->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
});

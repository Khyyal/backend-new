<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Models\User as CenterUser;
use Modules\Clients\Models\Client;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('scribe/openapi.yaml', "openapi: 3.1.0\n");
});

it('redirects guests to the docs login', function (string $uri) {
    $this->get($uri)->assertRedirect(route('docs.login'));
})->with(['/docs', '/docs.openapi', '/docs.postman']);

it('serves the spec to an active system user with api_docs.view', function () {
    $user = SystemUser::factory()->withPermissions([SystemPermission::ViewApiDocs])->create();

    $this->actingAs($user, 'system_user_web')->get('/docs.openapi')->assertOk();
});

it('returns 403 to a system user without api_docs.view', function () {
    $user = SystemUser::factory()->withPermissions([SystemPermission::ViewAnySystemUsers])->create();

    $this->actingAs($user, 'system_user_web')->get('/docs.openapi')->assertForbidden();
});

it('returns 403 to a system user deactivated after signing in', function () {
    $user = SystemUser::factory()->inactive()->withPermissions([SystemPermission::ViewApiDocs])->create();

    $this->actingAs($user, 'system_user_web')->get('/docs.openapi')->assertForbidden();
});

it('does not let client or center tokens open the docs', function (string $model, string $guard) {
    $this->actingAs($model::factory()->create(), $guard)->get('/docs.openapi')->assertRedirect(route('docs.login'));
})->with([
    'client' => [Client::class, 'client'],
    'center user' => [CenterUser::class, 'center_user'],
]);

it('signs an active system user in and sends them to the docs', function () {
    $user = SystemUser::factory()->withPermissions([SystemPermission::ViewApiDocs])->create();

    $this->post(route('docs.login.attempt'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('scribe'));

    $this->assertAuthenticatedAs($user, 'system_user_web');
});

it('rejects wrong credentials with an email error', function () {
    $user = SystemUser::factory()->create();

    $this->from(route('docs.login'))
        ->post(route('docs.login.attempt'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect(route('docs.login'))
        ->assertSessionHasErrors(['email' => __('support::auth.failed')]);

    $this->assertGuest('system_user_web');
});

it('rejects an inactive system user at login', function () {
    $user = SystemUser::factory()->inactive()->create();

    $this->post(route('docs.login.attempt'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('support::auth.inactive')]);

    $this->assertGuest('system_user_web');
});

it('requires email and password', function () {
    $this->post(route('docs.login.attempt'), [])->assertSessionHasErrors(['email', 'password']);
});

it('returns 429 after five failed attempts in a minute', function () {
    $user = SystemUser::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('docs.login.attempt'), ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post(route('docs.login.attempt'), ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();
});

it('signs the system user out', function () {
    $user = SystemUser::factory()->create();

    $this->actingAs($user, 'system_user_web')
        ->post(route('docs.logout'))
        ->assertRedirect(route('docs.login'));

    $this->assertGuest('system_user_web');
});

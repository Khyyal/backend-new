<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Centers\Models\User as CenterUser;
use Modules\Clients\Models\Client;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalStoragePath = app()->storagePath();
    $this->storage = sys_get_temp_dir().'/guide-'.uniqid();
    File::ensureDirectoryExists($this->storage.'/app/docs/assets');
    File::ensureDirectoryExists($this->storage.'/app/docs/dev');
    File::put($this->storage.'/app/docs/index.html', '<html><head><title>Docs</title></head><body>Home</body></html>');
    File::put($this->storage.'/app/docs/dev/index.html', '<html><head></head><body>Developer guide</body></html>');
    File::put($this->storage.'/app/docs/assets/app.js', 'console.log(1)');
    File::put($this->storage.'/secret.txt', 'secret');
    app()->useStoragePath($this->storage);
});

afterEach(function (): void {
    app()->useStoragePath($this->originalStoragePath);
    File::deleteDirectory($this->storage);
});

function guideReader(): SystemUser
{
    return SystemUser::factory()->withPermissions([SystemPermission::ViewApiDocs])->create();
}

it('redirects guests to the docs login', function (string $uri): void {
    $this->get($uri)->assertRedirect(route('docs.login'));
})->with(['/guide', '/guide/dev', '/guide/assets/app.js']);

it('does not let client or center tokens open the guide', function (string $model, string $guard): void {
    $this->actingAs($model::factory()->create(), $guard)->get('/guide')->assertRedirect(route('docs.login'));
})->with([
    'client' => [Client::class, 'client'],
    'center user' => [CenterUser::class, 'center_user'],
]);

it('returns 403 to a system user without api_docs.view', function (): void {
    $user = SystemUser::factory()->withPermissions([SystemPermission::ViewAnySystemUsers])->create();

    $this->actingAs($user, 'system_user_web')->get('/guide')->assertForbidden();
});

it('serves the index with a csrf token to an authorised system user', function (): void {
    $response = $this->actingAs(guideReader(), 'system_user_web')->get('/guide')->assertOk();

    expect($response->getContent())
        ->toContain('Home')
        ->toContain('<meta name="csrf-token" content="');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('resolves clean urls to html pages and folders', function (): void {
    $this->actingAs(guideReader(), 'system_user_web')
        ->get('/guide/dev')
        ->assertOk()
        ->assertSee('Developer guide');
});

it('serves assets with a long cache', function (): void {
    $response = $this->actingAs(guideReader(), 'system_user_web')->get('/guide/assets/app.js')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/javascript');
    expect($response->headers->get('Cache-Control'))->toContain('immutable');
});

it('returns 404 for unknown paths and path traversal', function (string $path): void {
    $this->actingAs(guideReader(), 'system_user_web')->get($path)->assertNotFound();
})->with(['/guide/missing', '/guide/..%2F..%2Fsecret.txt', '/guide/assets/../../../secret.txt']);

it('explains that the site has not been built', function (): void {
    File::deleteDirectory($this->storage.'/app/docs');

    $this->actingAs(guideReader(), 'system_user_web')
        ->get('/guide')
        ->assertNotFound()
        ->assertSee('has not been built yet');
});

it('keeps the endpoint reference login and logout working', function (): void {
    $user = guideReader();

    $this->post(route('docs.login.attempt'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('scribe'));
    $this->actingAs($user, 'system_user_web')->post(route('docs.logout'))->assertRedirect(route('docs.login'));
});

it('sends a guest back to the guide page after logging in', function (): void {
    $user = guideReader();

    $this->get('/guide/dev')->assertRedirect(route('docs.login'));
    $this->post(route('docs.login.attempt'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/guide/dev');
});

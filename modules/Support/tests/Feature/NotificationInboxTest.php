<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\UserFactory as CenterUserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Clients\Notifications\ClientRegisteredNotification;
use Modules\Support\Database\Factories\SystemUserFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->client = ClientFactory::new()->create();
    $this->notify = fn ($notifiable) => $notifiable->notifyNow(new ClientRegisteredNotification($this->client));
});

test('client lists notifications localized to the request locale', function (): void {
    ($this->notify)($this->client);

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/notifications', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'client_registered')
        ->assertJsonPath('data.0.title', 'عميل جديد')
        ->assertJsonPath('data.0.payload.title.en', 'New client')
        ->assertJsonPath('data.0.payload.client_id', $this->client->id)
        ->assertJsonPath('data.0.read_at', null);
});

test('client filters by read state and counts unread', function (): void {
    ($this->notify)($this->client);
    ($this->notify)($this->client);
    $this->client->notifications()->first()->markAsRead();

    $this->actingAs($this->client, 'client');

    $this->getJson('/api/v1/clients/notifications?read=false')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/clients/notifications?read=true')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/clients/notifications/unread-count')->assertExactJson(['unread_count' => 1]);
});

test('client marks one and all notifications as read', function (): void {
    ($this->notify)($this->client);
    ($this->notify)($this->client);
    $first = $this->client->notifications()->first();

    $this->actingAs($this->client, 'client');

    $this->postJson("/api/v1/clients/notifications/{$first->id}/mark-as-read")
        ->assertOk()
        ->assertJsonPath('data.id', $first->id);

    expect($first->fresh()->read_at)->not->toBeNull();

    $this->postJson('/api/v1/clients/notifications/mark-all-as-read')->assertExactJson(['updated' => 1]);

    expect($this->client->unreadNotifications()->count())->toBe(0);
});

test('client cannot read another user notification', function (): void {
    $other = ClientFactory::new()->create();
    ($this->notify)($other);

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/notifications/'.$other->notifications()->first()->id.'/mark-as-read')
        ->assertNotFound();
});

test('inbox requires authentication', function (string $url): void {
    $this->getJson($url)->assertUnauthorized();
})->with([
    '/api/v1/clients/notifications',
    '/api/v1/centers/notifications',
    '/api/v1/admin/notifications',
]);

test('center users and admins read their own inbox', function (): void {
    $centerUser = CenterUserFactory::new()->create();
    $admin = SystemUserFactory::new()->create();
    ($this->notify)($centerUser);
    ($this->notify)($admin);

    $this->actingAs($centerUser, 'center_user')
        ->getJson('/api/v1/centers/notifications/unread-count')
        ->assertExactJson(['unread_count' => 1]);

    $this->actingAs($admin, 'system_user')
        ->getJson('/api/v1/admin/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

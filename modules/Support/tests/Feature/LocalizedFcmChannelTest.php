<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Clients\Notifications\ClientRegisteredNotification;
use Modules\Support\Database\Factories\DeviceFactory;
use Modules\Support\Notifications\Channels\LocalizedFcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->client = ClientFactory::new()->create();
    $this->sent = [];

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')
        ->andReturnUsing(function (FcmMessage $message, array $tokens): MulticastSendReport {
            $this->sent[] = ['title' => $message->notification->title, 'tokens' => $tokens];

            return MulticastSendReport::withItems([]);
        });
    $this->app->instance(Messaging::class, $messaging);
});

test('push is sent once per device locale in that locale', function (): void {
    DeviceFactory::new()->ownedBy($this->client)->create(['locale' => 'en', 'fcm_token' => 'en-1']);
    DeviceFactory::new()->ownedBy($this->client)->create(['locale' => 'ar', 'fcm_token' => 'ar-1']);
    DeviceFactory::new()->ownedBy($this->client)->create(['locale' => 'ar', 'fcm_token' => 'ar-2']);
    DeviceFactory::new()->ownedBy($this->client)->create(['locale' => 'ar', 'fcm_token' => null]);

    $this->client->notifyNow(new ClientRegisteredNotification($this->client));

    expect($this->sent)->toEqualCanonicalizing([
        ['title' => 'New client', 'tokens' => ['en-1']],
        ['title' => 'عميل جديد', 'tokens' => ['ar-1', 'ar-2']],
    ]);
    expect($this->client->notifications()->count())->toBe(1);
});

test('users without tokens only get the inbox entry', function (): void {
    $this->client->notifyNow(new ClientRegisteredNotification($this->client));

    expect($this->sent)->toBe([]);
    expect($this->client->notifications()->count())->toBe(1);
});

test('tokens firebase no longer knows are cleared', function (): void {
    $device = DeviceFactory::new()->ownedBy($this->client)->create(['fcm_token' => 'dead-token']);

    event(new NotificationFailed($this->client, new ClientRegisteredNotification($this->client), LocalizedFcmChannel::class, [
        'report' => SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'dead-token'), new NotFound),
    ]));

    expect($device->fresh()->fcm_token)->toBeNull();
});

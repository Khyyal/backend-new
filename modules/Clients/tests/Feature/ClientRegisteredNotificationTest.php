<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Clients\Notifications\ClientRegisteredNotification;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    $this->app->instance(SMSService::class, Mockery::mock(SMSService::class));

    $this->devPhone = '+966500000999';
    config()->set('otp.dev.enabled', true);
    config()->set('otp.dev.phones', [$this->devPhone]);
    config()->set('otp.dev.code', '1234');

    $this->admin = SystemUserFactory::new()->create();
    $this->inactiveAdmin = SystemUserFactory::new()->inactive()->create();
    $this->verify = fn () => $this->postJson('/api/v1/clients/auth/verify', [
        'phone_number' => $this->devPhone,
        'code' => '1234',
    ])->assertOk();
});

test('a new client notifies active admins', function (): void {
    ($this->verify)();

    Notification::assertSentTo($this->admin, ClientRegisteredNotification::class);
    Notification::assertNotSentTo($this->inactiveAdmin, ClientRegisteredNotification::class);
});

test('a returning client does not notify admins', function (): void {
    ClientFactory::new()->create(['phone_number' => $this->devPhone]);

    ($this->verify)();

    Notification::assertNothingSent();
});

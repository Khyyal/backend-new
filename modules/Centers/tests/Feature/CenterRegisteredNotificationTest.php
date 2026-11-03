<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Centers\Notifications\CenterRegisteredNotification;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Services\SMSService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a registered center notifies active admins', function (): void {
    Notification::fake();
    $this->app->instance(SMSService::class, Mockery::mock(SMSService::class));
    CityFactory::new()->create(['name' => ['en' => 'City', 'ar' => 'مدينة']]);
    config()->set('otp.dev.enabled', true);
    config()->set('otp.dev.phones', ['+966500000999']);
    config()->set('otp.dev.code', '123456');

    $admin = SystemUserFactory::new()->create();
    $inactiveAdmin = SystemUserFactory::new()->inactive()->create();

    $this->postJson('/api/v1/centers/register', [
        'name' => 'Phoenix Riding Center',
        'contact_phone' => '+966112345678',
        'user' => [
            'name' => 'Ahmad',
            'phone' => '+966500000999',
            'code' => '123456',
            'password' => 'validpassword123',
        ],
    ])->assertCreated();

    Notification::assertSentTo(
        $admin,
        CenterRegisteredNotification::class,
        fn (CenterRegisteredNotification $notification): bool => $notification->center->name === 'Phoenix Riding Center'
            && str_contains($notification->body('en'), 'Ahmad registered Phoenix Riding Center'),
    );
    Notification::assertNotSentTo($inactiveAdmin, CenterRegisteredNotification::class);
});

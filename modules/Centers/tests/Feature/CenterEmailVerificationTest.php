<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Mail\CenterEmailVerificationMail;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    RateLimiter::clear('center-email-send:1');

    $this->center = CenterFactory::new()->create(['email' => 'info@club.test']);
    $this->primary = UserFactory::new()->create();
    $this->member = UserFactory::new()->create();

    foreach ([[$this->primary, true], [$this->member, false]] as [$user, $isPrimary]) {
        $this->center->users()->attach($user->id, [
            'status' => ActivationStatus::ACTIVE->value,
            'joined_at' => now()->toDateString(),
            'is_primary' => $isPrimary,
        ]);
    }

    $this->url = "/api/v1/centers/{$this->center->id}";
    $this->sentCode = function (): string {
        $code = null;
        Mail::assertQueued(CenterEmailVerificationMail::class, function (CenterEmailVerificationMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return $code;
    };
});

test('guests, members and outsiders cannot manage the email verification', function (): void {
    $this->postJson("{$this->url}/email/send")->assertUnauthorized();

    $this->actingAs($this->member, 'center_user')->postJson("{$this->url}/email/send")->assertForbidden();
    $this->actingAs($this->member, 'center_user')->postJson("{$this->url}/email/verify", ['code' => '123456'])->assertForbidden();
    $this->actingAs(UserFactory::new()->create(), 'center_user')->postJson("{$this->url}/email/send")->assertForbidden();

    Mail::assertNothingQueued();
});

test('sends a hashed code that expires and verifies the email with it', function (): void {
    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/send")
        ->assertOk()
        ->assertJsonPath('expires_in_seconds', 600);

    $code = ($this->sentCode)();
    $center = $this->center->fresh();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($center->email_verification_code)->not->toBe($code)
        ->and(Hash::check($code, $center->email_verification_code))->toBeTrue()
        ->and($center->email_verification_expires_at->isFuture())->toBeTrue();

    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/verify", ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.email', 'info@club.test')
        ->assertJsonPath('data.email_verified_at', now()->toIso8601String());

    $center->refresh();

    expect($center->hasVerifiedEmail())->toBeTrue()
        ->and($center->email_verification_code)->toBeNull();
});

test('a wrong or expired code does not verify', function (): void {
    $this->actingAs($this->primary, 'center_user')->postJson("{$this->url}/email/send")->assertOk();
    $code = ($this->sentCode)();
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/verify", ['code' => $wrong])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/verify", ['code' => 'abc'])
        ->assertJsonValidationErrors('code');

    $this->travel(11)->minutes();

    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/verify", ['code' => $code])
        ->assertUnprocessable();

    expect($this->center->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('too many wrong codes are throttled', function (): void {
    $this->actingAs($this->primary, 'center_user')->postJson("{$this->url}/email/send")->assertOk();

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->primary, 'center_user')
            ->postJson("{$this->url}/email/verify", ['code' => '000000']);
    }

    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/verify", ['code' => '000000'])
        ->assertStatus(429);
});

test('a new code needs a minute and at most five go out per fifteen minutes', function (): void {
    $this->actingAs($this->primary, 'center_user');

    $this->postJson("{$this->url}/email/send")->assertOk();
    $this->postJson("{$this->url}/email/send")->assertStatus(429);

    foreach (range(1, 4) as $ignored) {
        $this->travel(61)->seconds();
        $this->postJson("{$this->url}/email/send")->assertOk();
    }

    $this->travel(61)->seconds();
    $this->postJson("{$this->url}/email/send")->assertStatus(429);
});

test('there must be an unverified email to verify', function (): void {
    $this->center->forceFill(['email' => null])->save();

    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/send")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->center->forceFill(['email' => 'info@club.test', 'email_verified_at' => now()])->save();

    $this->actingAs($this->primary, 'center_user')
        ->postJson("{$this->url}/email/send")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    Mail::assertNothingQueued();
});

test('changing the email clears the verification, keeping it does not', function (): void {
    $this->center->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($this->primary, 'center_user')
        ->patchJson($this->url, ['email' => 'info@club.test', 'name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.email_verified_at', now()->toIso8601String());

    $this->actingAs($this->primary, 'center_user')
        ->patchJson($this->url, ['email' => 'new@club.test'])
        ->assertOk()
        ->assertJsonPath('data.email_verified_at', null);

    expect($this->center->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('the code is never serialized', function (): void {
    $this->actingAs($this->primary, 'center_user')->postJson("{$this->url}/email/send")->assertOk();

    expect($this->center->fresh()->toArray())->not->toHaveKey('email_verification_code');
});

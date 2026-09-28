<?php

use Illuminate\Support\Facades\Validator;
use Modules\Billing\Http\Requests\AttachLimitsRequest;
use Modules\Billing\Http\Requests\StorePlanRequest;
use Modules\Billing\Http\Requests\CreateSubscriptionRequest;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    \Modules\Support\Database\Factories\CityFactory::new()->create();
});

test('StorePlanRequest validates billing_interval enum price numeric name required array and trial_days max 365', function (): void {
    $rules = (new StorePlanRequest())->rules();

    $failsInvalid = Validator::make([
        'name' => 'plain-string',
        'price' => -1,
        'billing_interval' => 'weekly',
        'trial_days' => 400,
    ], $rules);
    expect($failsInvalid->fails())->toBeTrue();
    expect($failsInvalid->errors()->has('name'))->toBeTrue();
    expect($failsInvalid->errors()->has('price'))->toBeTrue();
    expect($failsInvalid->errors()->has('billing_interval'))->toBeTrue();
    expect($failsInvalid->errors()->has('trial_days'))->toBeTrue();

    $passes = Validator::make([
        'name' => ['en' => 'Pro', 'ar' => 'برو'],
        'slug' => 'pro',
        'price' => 29.99,
        'billing_interval' => 'monthly',
        'trial_days' => 14,
        'status' => 'active',
    ], $rules);
    expect($passes->passes())->toBeTrue($passes->errors()->toJson());
});

test('AttachLimitsRequest enforces items array with required limit_id and integer value >=0', function (): void {
    $rules = (new AttachLimitsRequest())->rules();

    $fails = Validator::make([
        'items' => [
            ['limit_id' => null, 'value' => -5],
            ['limit_id' => 1],
        ],
    ], $rules);
    expect($fails->fails())->toBeTrue();

    $existingLimit = \Modules\Billing\Database\Factories\LimitFactory::new()->create();
    $passes = Validator::make([
        'items' => [
            ['limit_id' => $existingLimit->id, 'value' => 50],
            ['limit_id' => $existingLimit->id, 'value' => 0],
        ],
    ], $rules);
    expect($passes->passes())->toBeTrue($passes->errors()->toJson());
});

test('CreateSubscriptionRequest requires plan_id integer that exists in plans', function (): void {
    $rules = (new CreateSubscriptionRequest())->rules();

    $fails = Validator::make([], $rules);
    expect($fails->fails())->toBeTrue();
    expect($fails->errors()->has('plan_id'))->toBeTrue();

    $failsNotExist = Validator::make(['plan_id' => 999999], $rules);
    expect($failsNotExist->fails())->toBeTrue();

    $plan = \Modules\Billing\Database\Factories\PlanFactory::new()->create();
    $passes = Validator::make(['plan_id' => $plan->id], $rules);
    expect($passes->passes())->toBeTrue($passes->errors()->toJson());
});

test('StorePlanRequest requires name.en', function (): void {
    $rules = (new StorePlanRequest())->rules();
    $v = Validator::make([
        'name' => ['ar' => 'عربي'],
        'price' => 0,
        'billing_interval' => 'yearly',
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('name.en'))->toBeTrue();
});

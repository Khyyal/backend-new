<?php

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Modules\Promotions\Http\Controllers\Admin\CampaignController;
use Modules\Promotions\Http\Controllers\Admin\CouponController as AdminCouponController;
use Modules\Promotions\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use Modules\Promotions\Http\Controllers\Center\CouponController as CenterCouponController;
use Modules\Promotions\Http\Controllers\Center\PromotionController as CenterPromotionController;
use Modules\Promotions\Http\Controllers\Client\OfferController;

arch('promotion controllers document every endpoint')
    ->expect([
        AdminPromotionController::class,
        AdminCouponController::class,
        CampaignController::class,
        CenterPromotionController::class,
        CenterCouponController::class,
        OfferController::class,
    ])
    ->toHaveAttribute(Group::class);

test('every public endpoint of the promotion controllers declares a Response', function (string $controller): void {
    $reflection = new ReflectionClass($controller);

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $controller || $method->isConstructor()) {
            continue;
        }

        expect($method->getAttributes(Response::class))->not->toBeEmpty("{$controller}::{$method->getName()} has no Response attribute");
    }
})->with([
    AdminPromotionController::class,
    AdminCouponController::class,
    CampaignController::class,
    CenterPromotionController::class,
    CenterCouponController::class,
    OfferController::class,
]);

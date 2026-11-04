<?php

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Modules\Homepage\Http\Controllers\Admin\AnalyticsController;
use Modules\Homepage\Http\Controllers\Admin\BannerController;
use Modules\Homepage\Http\Controllers\Admin\HomeSectionController;
use Modules\Homepage\Http\Controllers\Client\HomeController;

arch('homepage controllers document every endpoint')
    ->expect([HomeSectionController::class, BannerController::class, AnalyticsController::class, HomeController::class])
    ->toHaveAttribute(Group::class);

test('every public endpoint of the homepage controllers declares a Response', function (string $controller): void {
    $reflection = new ReflectionClass($controller);

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $controller || $method->isConstructor()) {
            continue;
        }

        expect($method->getAttributes(Response::class))->not->toBeEmpty("{$controller}::{$method->getName()} has no Response attribute");
    }
})->with([
    HomeSectionController::class,
    BannerController::class,
    AnalyticsController::class,
    HomeController::class,
]);

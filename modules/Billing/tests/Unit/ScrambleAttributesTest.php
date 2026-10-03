<?php

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Modules\Billing\Http\Controllers\Admin\FeatureController as AdminFeatureController;
use Modules\Billing\Http\Controllers\Admin\LimitController as AdminLimitController;
use Modules\Billing\Http\Controllers\Admin\PlanController as AdminPlanController;
use Modules\Billing\Http\Controllers\Center\PlanController as CenterPlanController;
use Modules\Billing\Http\Controllers\Center\SubscriptionController as CenterSubscriptionController;
use ReflectionClass;
use ReflectionMethod;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

if (! function_exists('publicControllerMethods')) {
    function publicControllerMethods(string $class): array
    {
        $r = new ReflectionClass($class);
        $exclude = [
            'middleware', 'getMiddleware', 'callAction', '__call', '__callStatic',
            'authorize', 'authorizeResource', 'validate', 'validateWith', 'validateWithBag',
        ];

        return array_values(array_filter(
            array_map(fn (ReflectionMethod $m) => $m->getName(), $r->getMethods(ReflectionMethod::IS_PUBLIC)),
            fn (string $n) => ! str_starts_with($n, '__') && ! in_array($n, $exclude, true),
        ));
    }
}

if (! function_exists('methodHasAttr')) {
    function methodHasAttr(string $class, string $method, string $attr): bool
    {
        $r = new ReflectionMethod($class, $method);

        return count($r->getAttributes($attr)) > 0;
    }
}

it('has class-level Group attribute on every controller across admin and center', function (string $cls): void {
    $r = new ReflectionClass($cls);
    expect(count($r->getAttributes(Group::class)))->toBeGreaterThan(0, "$cls missing #[Group]");
})->with([
    AdminPlanController::class,
    AdminFeatureController::class,
    AdminLimitController::class,
    CenterPlanController::class,
    CenterSubscriptionController::class,
]);

it('every public method on Admin PlanController has at least one Response attribute', function (): void {
    foreach (publicControllerMethods(AdminPlanController::class) as $m) {
        expect(methodHasAttr(AdminPlanController::class, $m, Response::class))
            ->toBeTrue("AdminPlanController::$m missing #[Response]");
    }
});

it('every public method on Admin FeatureController has Response attribute', function (): void {
    foreach (publicControllerMethods(AdminFeatureController::class) as $m) {
        expect(methodHasAttr(AdminFeatureController::class, $m, Response::class))
            ->toBeTrue("AdminFeatureController::$m missing #[Response]");
    }
});

it('every public method on Admin LimitController has Response attribute', function (): void {
    foreach (publicControllerMethods(AdminLimitController::class) as $m) {
        expect(methodHasAttr(AdminLimitController::class, $m, Response::class))
            ->toBeTrue("AdminLimitController::$m missing #[Response]");
    }
});

it('every public method on Center PlanController has Response attribute', function (): void {
    foreach (publicControllerMethods(CenterPlanController::class) as $m) {
        expect(methodHasAttr(CenterPlanController::class, $m, Response::class))
            ->toBeTrue("CenterPlanController::$m missing #[Response]");
    }
});

it('every public method on Center SubscriptionController has Response attribute', function (): void {
    foreach (publicControllerMethods(CenterSubscriptionController::class) as $m) {
        expect(methodHasAttr(CenterSubscriptionController::class, $m, Response::class))
            ->toBeTrue("CenterSubscriptionController::$m missing #[Response]");
    }
});

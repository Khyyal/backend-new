<?php

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Modules\Promotion\Http\Controllers\Admin\DiscountController as AdminDiscountController;
use Modules\Promotion\Http\Controllers\Center\DiscountController as CenterDiscountController;
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

test('admin discount controller has class-level Group attribute', function (): void {
    $r = new ReflectionClass(AdminDiscountController::class);
    expect(count($r->getAttributes(Group::class)))->toBeGreaterThan(0);
});

test('center discount controller has class-level Group attribute', function (): void {
    $r = new ReflectionClass(CenterDiscountController::class);
    expect(count($r->getAttributes(Group::class)))->toBeGreaterThan(0);
});

test('every public method on admin discount controller has at least one Response attribute', function (): void {
    foreach (publicControllerMethods(AdminDiscountController::class) as $m) {
        expect(methodHasAttr(AdminDiscountController::class, $m, Response::class))
            ->toBeTrue("AdminDiscountController::$m missing Response attribute");
    }
});

test('every public method on center discount controller has at least one Response attribute', function (): void {
    foreach (publicControllerMethods(CenterDiscountController::class) as $m) {
        expect(methodHasAttr(CenterDiscountController::class, $m, Response::class))
            ->toBeTrue("CenterDiscountController::$m missing Response attribute");
    }
});

<?php

use Modules\Support\Support\Haversine;
use Tests\TestCase;

uses(TestCase::class);

test('uses LEAST and GREATEST on mysql', function (): void {
    $sql = Haversine::sql('centers.lat', 'centers.lng', 'mysql');

    expect($sql)->toContain('LEAST(1, GREATEST(-1,')
        ->not->toContain('MIN(')
        ->not->toContain('MAX(');
});

test('uses scalar MIN and MAX on sqlite', function (): void {
    expect(Haversine::sql('lat', 'lng', 'sqlite'))->toContain('MIN(1, MAX(-1,');
});

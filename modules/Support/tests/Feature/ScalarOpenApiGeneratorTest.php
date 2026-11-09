<?php

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Modules\Support\Documentation\ScalarOpenApiGenerator;
use Tests\TestCase;

uses(TestCase::class);

function scalarEndpoint(string $method, string $uri, string $group): OutputEndpointData
{
    return new OutputEndpointData([
        'httpMethods' => [$method],
        'uri' => $uri,
        'metadata' => ['groupName' => $group, 'groupDescription' => '', 'title' => 'Endpoint', 'description' => '', 'authenticated' => true],
    ]);
}

function scalarGenerator(): ScalarOpenApiGenerator
{
    return new ScalarOpenApiGenerator(new DocumentationConfig(config('scribe')));
}

it('sets each operation security from the route guard', function (string $method, string $uri, array $security) {
    $pathItem = scalarGenerator()->pathItem([], [], scalarEndpoint($method, $uri, 'Promotions'));

    expect($pathItem['security'])->toBe($security);
})->with([
    'system user' => ['GET', 'api/v1/admin/promotions', [['systemUserToken' => []]]],
    'center user' => ['GET', 'api/v1/centers/{center}/promotions', [['centerUserToken' => []]]],
    'client' => ['GET', 'api/v1/clients/offers', [['clientToken' => []]]],
    'public' => ['POST', 'api/v1/admin/auth/login', []],
    'several guards' => ['POST', 'api/v1/media', [['clientToken' => []], ['centerUserToken' => []], ['systemUserToken' => []]]],
]);

it('flags center-scoped operations and explains the required ability', function () {
    $pathItem = scalarGenerator()->pathItem([], [], scalarEndpoint('GET', 'api/v1/centers/{center}/promotions', 'Center / Promotions'));

    expect($pathItem['x-center-scoped'])->toBeTrue()
        ->and($pathItem['description'])->toContain('center-access:{center}');
});

it('tags operations by the actor of their URI', function () {
    $pathItem = scalarGenerator()->pathItem([], [], scalarEndpoint('GET', 'api/v1/admin/centers/{center}/blocked-clients', 'Center / Blocked clients'));

    expect($pathItem['tags'])->toBe(['Admin / Blocked clients']);
});

it('groups routes by actor with or without an api version prefix', function (string $uri, string $actor) {
    expect(ScalarOpenApiGenerator::actorForUri($uri))->toBe($actor);
})->with([
    'versioned admin' => ['api/v1/admin/promotions', 'Admin'],
    'unversioned admin' => ['api/admin/plans', 'Admin'],
    'versioned center' => ['api/v1/centers/{center}/promotions', 'Center'],
    'unversioned center' => ['api/center/subscriptions', 'Center'],
    'client' => ['api/v1/clients/offers', 'Client'],
    'shared media' => ['api/v1/media', 'Public'],
    'webhooks' => ['api/v1/webhooks/moyasar', 'Public'],
]);

it('replaces the default scheme and groups tags by actor', function () {
    $root = scalarGenerator()->root(
        ['components' => ['securitySchemes' => ['default' => ['type' => 'http']]], 'security' => [['default' => []]]],
        [[
            'name' => 'Promotions',
            'description' => 'Promotions.',
            'endpoints' => [
                scalarEndpoint('GET', 'api/v1/admin/promotions', 'Admin / Promotions'),
                scalarEndpoint('GET', 'api/v1/centers/{center}/promotions', 'Center / Promotions'),
                scalarEndpoint('POST', 'api/v1/webhooks/moyasar', 'Webhooks'),
            ],
        ]],
    );

    expect(array_keys($root['components']['securitySchemes']))->toBe(['clientToken', 'centerUserToken', 'systemUserToken'])
        ->and($root)->not->toHaveKey('security')
        ->and($root['x-tagGroups'])->toBe([
            ['name' => 'Admin', 'tags' => ['Admin / Promotions']],
            ['name' => 'Center', 'tags' => ['Center / Promotions']],
            ['name' => 'Public', 'tags' => ['Public / Webhooks']],
        ]);
});

<?php

namespace Modules\Support\Documentation;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Shapes Scribe's OpenAPI document for the Scalar UI.
 *
 * Scribe only knows a single auth scheme, while this API has one Sanctum guard per actor. This generator
 * declares a bearer scheme per guard, derives each operation's security from its route middleware, and
 * groups the sidebar by actor (Admin / Center / Client / Public) through Scalar's `x-tagGroups`.
 */
class ScalarOpenApiGenerator extends OpenApiGenerator
{
    /**
     * Guard name => OpenAPI security scheme name.
     *
     * @var array<string, string>
     */
    public const GUARD_SCHEMES = [
        'client' => 'clientToken',
        'center_user' => 'centerUserToken',
        'system_user' => 'systemUserToken',
    ];

    /**
     * Actors in sidebar order.
     *
     * @var list<string>
     */
    public const ACTORS = ['Admin', 'Center', 'Client', 'Public'];

    /** @var array<string, Route>|null */
    private ?array $routesByKey = null;

    /**
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     */
    public function root(array $root, array $groupedEndpoints): array
    {
        unset($root['components']['securitySchemes']['default']);
        $root['components']['securitySchemes'] = [
            ...($root['components']['securitySchemes'] ?? []),
            ...self::securitySchemes(),
        ];
        unset($root['security']);

        $tags = [];
        $tagGroups = array_fill_keys(self::ACTORS, []);

        foreach ($groupedEndpoints as $group) {
            foreach ($group['endpoints'] as $endpoint) {
                $tag = $this->tagFor($endpoint, $endpoint->metadata->groupName ?? $group['name']);
                $tags[$tag] ??= ['name' => $tag, 'description' => $group['description'] ?? ''];
                $tagGroups[self::actorForUri($endpoint->uri)][$tag] = $tag;
            }
        }

        ksort($tags);
        $root['tags'] = array_values($tags);
        $root['x-tagGroups'] = collect($tagGroups)
            ->filter()
            ->map(fn (array $groupTags, string $actor): array => ['name' => $actor, 'tags' => collect($groupTags)->sort()->values()->all()])
            ->values()
            ->all();

        return $root;
    }

    /**
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        $pathItem['tags'] = [$this->tagFor($endpoint, $endpoint->metadata->groupName ?? 'Undocumented')];

        $route = $this->routeFor($endpoint);
        $middleware = $route?->gatherMiddleware() ?? [];
        $guards = self::guardsFromMiddleware($middleware);

        $pathItem['security'] = collect($guards)
            ->map(fn (string $guard): ?string => self::GUARD_SCHEMES[$guard] ?? null)
            ->filter()
            ->map(fn (string $scheme): array => [$scheme => []])
            ->values()
            ->all();

        if (in_array('center.scope', $middleware, true)) {
            $pathItem['x-center-scoped'] = true;
            $pathItem['description'] = trim(($pathItem['description'] ?? '')."\n\n"
                .'**Center-scoped:** the center-user token must carry the `center-access:{center}` ability (from `POST /api/v1/centers/{center}/access`) and the center must be active.');
        }

        return $pathItem;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function securitySchemes(): array
    {
        return [
            'clientToken' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'description' => 'Client (mobile app) Sanctum token from `POST /api/v1/clients/auth/verify` (OTP verification).',
            ],
            'centerUserToken' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'description' => 'Center-user Sanctum token. `POST /api/v1/centers/auth/phone/verify` returns a token for the account-level routes; `POST /api/v1/centers/{center}/access` returns a token with the `center-access:{center}` ability required by every `centers/{center}/*` route.',
            ],
            'systemUserToken' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'description' => 'System user (admin panel) Sanctum token from `POST /api/v1/admin/auth/login`.',
            ],
        ];
    }

    /**
     * @param  array<int, string>  $middleware
     * @return list<string>
     */
    public static function guardsFromMiddleware(array $middleware): array
    {
        return collect($middleware)
            ->filter(fn (string $name): bool => Str::startsWith($name, ['auth:', Authenticate::class.':']))
            ->flatMap(fn (string $name): array => explode(',', Str::after($name, ':')))
            ->map(fn (string $guard): string => trim($guard))
            ->unique()
            ->values()
            ->all();
    }

    public static function actorForUri(string $uri): string
    {
        // Routes live under `api/v1/…`, but some (the billing catalog and subscriptions) are mounted under `api/…` without a version.
        $segment = Str::before((string) preg_replace('#^(api/)?(v\d+/)?#', '', ltrim($uri, '/')), '/');

        return match ($segment) {
            'admin' => 'Admin',
            'center', 'centers' => 'Center',
            'client', 'clients' => 'Client',
            default => 'Public',
        };
    }

    /**
     * Tag = "{actor} / {resource}". The actor comes from the URI, so a controller shared by the admin and center
     * portals lands in both sidebars; the resource is the #[Group] name without any actor prefix.
     */
    private function tagFor(OutputEndpointData $endpoint, string $groupName): string
    {
        $resource = preg_replace('/^(Admin|Center|Client|Public)\s*\/\s*/', '', $groupName);

        return self::actorForUri($endpoint->uri).' / '.$resource;
    }

    private function routeFor(OutputEndpointData $endpoint): ?Route
    {
        if ($this->routesByKey === null) {
            $this->routesByKey = [];

            foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
                foreach ($route->methods() as $method) {
                    $this->routesByKey[$method.' '.self::normalizeUri($route->uri())] = $route;
                }
            }
        }

        return $this->routesByKey[$endpoint->httpMethods[0].' '.self::normalizeUri($endpoint->uri)] ?? null;
    }

    private static function normalizeUri(string $uri): string
    {
        return preg_replace('/\{(\w+)(:\w+)?\??\}/', '{$1}', ltrim($uri, '/'));
    }
}

<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;
use Modules\Support\Documentation\ScalarOpenApiGenerator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Runs $check for every API route and returns the problems it reports, prefixed with the route.
 *
 * @param  callable(Route, ReflectionMethod): list<string>  $check
 * @return list<string>
 */
function apiDocumentationProblems(callable $check): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => Str::startsWith($route->uri(), 'api/') && is_string($route->getAction('controller')))
        ->flatMap(fn (Route $route): array => array_map(
            fn (string $problem): string => '['.Str::before(Str::after($route->getAction('controller'), 'Modules\\'), '\\').'] '.$route->methods()[0].' '.$route->uri().': '.$problem,
            $check($route, routeMethod($route)),
        ))
        ->values()
        ->all();
}

function routeMethod(Route $route): ReflectionMethod
{
    [$class, $method] = Str::parseCallback($route->getAction('controller'), '__invoke');

    return new ReflectionMethod($class, $method);
}

/**
 * @return list<string>
 */
function attributeNames(ReflectionMethod|ReflectionClass $target, string $attribute): array
{
    return array_map(fn (ReflectionAttribute $a): string => $a->newInstance()->name, $target->getAttributes($attribute));
}

function formRequestOf(ReflectionMethod $method): ?string
{
    foreach ($method->getParameters() as $parameter) {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
            return $type->getName();
        }
    }

    return null;
}

/**
 * Scribe parameter name for a validation key: `a.*.b` => `a[].b`, `a.*` is covered by `a`.
 */
function scribeParamName(string $ruleKey): ?string
{
    if (Str::endsWith($ruleKey, '.*')) {
        return null;
    }

    return str_replace('.*.', '[].', $ruleKey);
}

it('documents the controller, endpoint, success response and URL params of every API route', function () {
    $problems = apiDocumentationProblems(function (Route $route, ReflectionMethod $method): array {
        $problems = [];
        if ($method->getDeclaringClass()->getAttributes(Group::class) === []) {
            $problems[] = 'controller has no #[Group]';
        }
        if ($method->getAttributes(Endpoint::class) === []) {
            $problems[] = 'no #[Endpoint]';
        }
        $statuses = [
            ...array_map(fn (ReflectionAttribute $a): int => $a->newInstance()->status, $method->getAttributes(Response::class)),
            ...array_map(fn (ReflectionAttribute $a): int => $a->newInstance()->status, $method->getAttributes(ResponseFromApiResource::class)),
        ];
        if (! collect($statuses)->contains(fn (int $status): bool => $status >= 200 && $status < 400)) {
            $problems[] = 'no success #[Response] or #[ResponseFromApiResource]';
        }
        foreach (array_diff($route->parameterNames(), attributeNames($method, UrlParam::class)) as $parameter) {
            $problems[] = "no #[UrlParam('{$parameter}')]";
        }

        return $problems;
    });

    expect($problems)->toBeEmpty(implode("\n", $problems));
});

it('marks exactly the API routes without auth middleware as unauthenticated', function () {
    $problems = apiDocumentationProblems(function (Route $route, ReflectionMethod $method): array {
        $isPublic = ScalarOpenApiGenerator::guardsFromMiddleware($route->gatherMiddleware()) === [];
        $isMarked = $method->getAttributes(Unauthenticated::class) !== []
            || $method->getDeclaringClass()->getAttributes(Unauthenticated::class) !== [];

        return match (true) {
            $isPublic && ! $isMarked => ['public route lacks #[Unauthenticated]'],
            ! $isPublic && $isMarked => ['authenticated route has #[Unauthenticated]'],
            default => [],
        };
    });

    expect($problems)->toBeEmpty(implode("\n", $problems));
});

it('documents every validated field of each API form request', function () {
    $problems = apiDocumentationProblems(function (Route $route, ReflectionMethod $method): array {
        $formRequest = formRequestOf($method);
        if ($formRequest === null) {
            return [];
        }

        /** @var FormRequest $request */
        $request = new $formRequest;
        $boundRoute = (clone $route)->bind(Request::create('/'.$route->uri()));
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $routeParameter = collect($route->parameterNames())->first(fn (string $name): bool => Str::camel($name) === $parameter->getName());
            if ($routeParameter !== null && $type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Model::class)) {
                $boundRoute->setParameter($routeParameter, new ($type->getName()));
            }
        }
        $request->setContainer(app())->setRouteResolver(fn (): Route => $boundRoute);

        try {
            $rules = $request->rules();
        } catch (Throwable $exception) {
            return [class_basename($formRequest).' rules() could not be evaluated: '.$exception->getMessage()];
        }

        $reflection = new ReflectionClass($formRequest);
        $documented = [
            ...attributeNames($reflection, BodyParam::class),
            ...attributeNames($reflection, QueryParam::class),
            ...attributeNames($method, BodyParam::class),
            ...attributeNames($method, QueryParam::class),
        ];

        return collect(array_keys($rules))
            ->map(scribeParamName(...))
            ->filter()
            ->diff($documented)
            ->map(fn (string $field): string => class_basename($formRequest)." does not document `{$field}`")
            ->values()
            ->all();
    });

    expect(array_values(array_unique($problems)))->toBeEmpty(implode("\n", array_unique($problems)));
});

it('leaves no Scramble attributes or docblock descriptions in API controllers', function () {
    $problems = apiDocumentationProblems(function (Route $route, ReflectionMethod $method): array {
        $problems = [];
        if (str_contains((string) file_get_contents($method->getFileName()), 'Dedoc\\Scramble')) {
            $problems[] = class_basename($method->class).' still imports Scramble';
        }
        if (preg_match('/^\s*\/\*\*\s*\n\s*\*\s+[A-Z]/', (string) $method->getDocComment())) {
            $problems[] = "{$method->name}() describes itself in a docblock instead of #[Endpoint]";
        }

        return $problems;
    });

    expect($problems)->toBeEmpty(implode("\n", $problems));
});

it('builds every documentation attribute of the API without errors', function () {
    $problems = apiDocumentationProblems(function (Route $route, ReflectionMethod $method): array {
        $targets = [$method, $method->getDeclaringClass()];
        if (($formRequest = formRequestOf($method)) !== null) {
            $targets[] = new ReflectionClass($formRequest);
        }

        $problems = [];
        foreach ($targets as $target) {
            foreach ($target->getAttributes() as $attribute) {
                if (! str_starts_with($attribute->getName(), 'Knuckles\\Scribe\\Attributes\\')) {
                    continue;
                }
                try {
                    $instance = $attribute->newInstance();
                    if (method_exists($instance, 'toArray')) {
                        $instance->toArray();
                    }
                } catch (Throwable $exception) {
                    $problems[] = class_basename($attribute->getName()).' on '.$target->getName().': '.$exception->getMessage();
                }
            }
        }

        return $problems;
    });

    expect(array_values(array_unique($problems)))->toBeEmpty(implode("\n", array_unique($problems)));
});

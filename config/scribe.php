<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Extracting\Strategies;
use Modules\Support\Documentation\ScalarOpenApiGenerator;
use Modules\Support\Documentation\Strategies\GetDocumentedResponseFields;
use Modules\Support\Documentation\Strategies\UseDocumentedResourceResponses;

/*
 * Documentation is attribute-only: docblock/tag strategies and response calls are disabled,
 * so every endpoint must describe itself with Knuckles\Scribe\Attributes\*.
 */
$intro = <<<'INTRO'
    REST API for the Khyyal client app, center portal and admin panel.

    Endpoints are served under `/api/v1`, except the plan catalog and subscriptions under `/api/admin/*` and `/api/center/*`.
    They speak JSON: send `Accept: application/json` on every request.

    Three actors authenticate with Sanctum bearer tokens, each with its own guard and route prefix:
    - **Client** (`clients/*`): token from the client OTP login.
    - **Center user** (`centers/*`, `center/*`): the login token covers account-level routes; `centers/{center}/*` routes need the token from
      `POST /api/v1/centers/{center}/access`, which carries the `center-access:{center}` ability.
    - **System user** (`admin/*`): token from `POST /api/v1/admin/auth/login`.

    Amounts are integer halalas (1/100 SAR) and percentages are basis points (10000 = 100%). Dates are UTC ISO-8601.
    Translatable fields are objects keyed by `en` and `ar`; client-facing endpoints return the `Accept-Language` locale.

    Paginated lists return `data` (the items), `links` (`first`, `last`, `prev`, `next`) and `meta`
    (`current_page`, `from`, `last_page`, `path`, `per_page`, `to`, `total`). Errors return `message`, plus `errors`
    (field => messages) on 422.
    INTRO;

return [
    'title' => config('app.name').' API',

    // External UIs (Scalar) only show `description`, so it carries the whole introduction.
    'description' => $intro,

    'intro_text' => $intro,

    'base_url' => config('app.url'),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [],
        ],
    ],

    'type' => 'external_laravel',

    'theme' => 'scalar',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'assets_directory' => null,
        'middleware' => ['web', 'auth:system_user_web', 'can:viewApiDocs'],
    ],

    'external' => [
        'html_attributes' => [
            'data-configuration' => e(json_encode([
                'theme' => 'laravel',
                'darkMode' => false,
                'showDeveloperTools' => 'never',
                'agent' => ['disabled' => true],
                'hideClientButton' => false,
                'defaultOpenAllTags' => false,
                'persistAuth' => true,
            ], JSON_THROW_ON_ERROR)),
        ],
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => null,
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => true,
        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',
        'use_value' => null,
        'placeholder' => '{TOKEN}',
        'extra_info' => 'Each actor has its own token type; see the security scheme shown on every endpoint.',
    ],

    'example_languages' => [
        'bash',
        'javascript',
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [],
    ],

    'openapi' => [
        'enabled' => true,
        'version' => '3.1.0',
        'overrides' => [
            'info.version' => env('API_VERSION', '1.0.0'),
        ],
        'generators' => [
            ScalarOpenApiGenerator::class,
        ],
    ],

    'groups' => [
        // Any endpoint landing here is missing a #[Group] attribute; the documentation tests fail on it.
        'default' => 'Undocumented',
        'order' => [],
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    // Resource examples are built from #[ResponseField] examples (UseDocumentedResourceResponses), never from models.
    'examples' => [
        'faker_seed' => 1234,
        'models_source' => [],
    ],

    'strategies' => [
        'metadata' => [
            Strategies\Metadata\GetFromMetadataAttributes::class,
        ],
        'headers' => [
            Strategies\Headers\GetFromHeaderAttribute::class,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            Strategies\UrlParameters\GetFromLaravelAPI::class,
            Strategies\UrlParameters\GetFromUrlParamAttribute::class,
        ],
        'queryParameters' => [
            Strategies\QueryParameters\GetFromQueryParamAttribute::class,
        ],
        'bodyParameters' => [
            Strategies\BodyParameters\GetFromBodyParamAttribute::class,
        ],
        'responses' => [
            UseDocumentedResourceResponses::class,
        ],
        'responseFields' => [
            GetDocumentedResponseFields::class,
        ],
    ],

    'database_connections_to_transact' => [],

    'fractal' => [
        'serializer' => null,
    ],
];

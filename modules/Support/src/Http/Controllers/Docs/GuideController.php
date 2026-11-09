<?php

namespace Modules\Support\Http\Controllers\Docs;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves the built VitePress documentation site (docs-site) from storage/app/docs.
 */
class GuideController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const CONTENT_TYPES = [
        'html' => 'text/html; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'mjs' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json',
        'map' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'txt' => 'text/plain; charset=UTF-8',
        'xml' => 'application/xml',
    ];

    public function show(?string $path = null): SymfonyResponse
    {
        $root = realpath(storage_path('app/docs'));

        if ($root === false || ! is_file($root.'/index.html')) {
            return new Response('The documentation has not been built yet. Run `npm run docs:build` and commit storage/app/docs.', SymfonyResponse::HTTP_NOT_FOUND, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        $file = $this->resolveFile($root, trim((string) $path, '/'));

        if ($file === null) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $contentType = self::CONTENT_TYPES[$extension] ?? 'application/octet-stream';

        if ($extension === 'html') {
            return new Response($this->injectCsrfToken((string) file_get_contents($file)), SymfonyResponse::HTTP_OK, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, no-store',
            ]);
        }

        $cacheControl = str_starts_with($file, $root.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR)
            ? 'private, max-age=31536000, immutable'
            : 'private, no-cache';

        return response()->file($file, [
            'Content-Type' => $contentType,
            'Cache-Control' => $cacheControl,
        ]);
    }

    /**
     * Maps a request path to a file inside the docs root, honouring VitePress clean URLs.
     */
    private function resolveFile(string $root, string $path): ?string
    {
        $candidates = $path === ''
            ? ['index.html']
            : [$path, $path.'.html', $path.'/index.html'];

        foreach ($candidates as $candidate) {
            $resolved = realpath($root.DIRECTORY_SEPARATOR.$candidate);

            if ($resolved !== false && is_file($resolved) && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * The sign-out button in the docs theme reads this token from the page.
     */
    private function injectCsrfToken(string $html): string
    {
        $meta = '<meta name="csrf-token" content="'.e(csrf_token()).'">';

        $injected = preg_replace('~</head>~i', $meta.'</head>', $html, 1, $count);

        return $count > 0 && $injected !== null ? $injected : $meta.$html;
    }
}

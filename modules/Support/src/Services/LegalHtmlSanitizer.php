<?php

namespace Modules\Support\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Cleans admin-written legal HTML before it is stored: keeps safe formatting (headings, lists, links, tables,
 * text alignment) and drops scripts, event handlers and unsafe URLs, since the pages open inside app webviews.
 */
class LegalHtmlSanitizer
{
    public const MAX_LENGTH = 200_000;

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowAttribute('style', ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th'])
                ->allowAttribute('dir', '*')
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withAttributeSanitizer(new class implements AttributeSanitizerInterface
                {
                    public function getSupportedElements(): ?array
                    {
                        return null;
                    }

                    public function getSupportedAttributes(): ?array
                    {
                        return ['style'];
                    }

                    /**
                     * Keeps only a text-align declaration (what the panel editor writes) and drops any other style.
                     */
                    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                    {
                        return preg_match('/text-align\s*:\s*(left|right|center|justify|start|end)/i', $value, $match) === 1
                            ? 'text-align: '.strtolower($match[1])
                            : null;
                    }
                })
                ->withMaxInputLength(self::MAX_LENGTH)
        );
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}

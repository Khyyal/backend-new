<?php

namespace Modules\Search\Support;

use Modules\Purchase\Models\Purchase;

/**
 * The normalised search input with helpers for LIKE patterns and public codes.
 */
final readonly class SearchTerm
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = trim($value);
    }

    /**
     * `%term%` with LIKE wildcards escaped.
     */
    public function like(): string
    {
        return '%'.addcslashes($this->value, '%_\\').'%';
    }

    /**
     * Whether the term could be a public reservation/purchase code (see Modules\Support\Concerns\HasUniqueCode).
     */
    public function looksLikeCode(): bool
    {
        $alphabet = preg_quote(Purchase::CODE_ALPHABET, '/');

        return (bool) preg_match('/^['.$alphabet.']{'.Purchase::CODE_LENGTH.'}$/', $this->code());
    }

    public function code(): string
    {
        return strtoupper($this->value);
    }
}

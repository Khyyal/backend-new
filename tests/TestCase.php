<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Support\Http\Middleware\EnsureIdempotentRequest;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whether JSON write requests get a fresh Idempotency-Key when the test
     * does not send one, so tests that are not about idempotency need not.
     */
    protected bool $sendsIdempotencyKey = true;

    /**
     * Sends JSON write requests without an automatic Idempotency-Key.
     */
    public function withoutIdempotencyKey(): static
    {
        $this->sendsIdempotencyKey = false;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $headers
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        if ($this->sendsIdempotencyKey && $this->isWriteMethod($method) && ! $this->hasIdempotencyKey($headers)) {
            $headers[EnsureIdempotentRequest::HEADER] = (string) Str::uuid();
        }

        return parent::json($method, $uri, $data, $headers, $options);
    }

    private function isWriteMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function hasIdempotencyKey(array $headers): bool
    {
        $sent = array_change_key_case([...$this->defaultHeaders, ...$headers]);

        return array_key_exists(strtolower(EnsureIdempotentRequest::HEADER), $sent);
    }
}

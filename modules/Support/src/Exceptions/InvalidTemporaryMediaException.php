<?php

namespace Modules\Support\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown for every reason a temporary media cannot be used (missing, foreign,
 * expired, already promoted) so callers cannot probe which one applies.
 */
class InvalidTemporaryMediaException extends HttpException
{
    public function __construct()
    {
        parent::__construct(422, 'The given media is invalid, expired or already used.');
    }
}

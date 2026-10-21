<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a chat request cannot reserve its daily quota, so the controller
 * can answer 429 instead of calling the model.
 */
class QuotaExceededException extends Exception
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}

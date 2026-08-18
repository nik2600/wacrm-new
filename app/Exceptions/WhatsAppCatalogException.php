<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a catalog provider call fails. Wraps Meta/360dialog
 * error responses so callers don't need to parse provider-specific
 * shapes — message + code are normalised, raw response sits on
 * ->context() for the rare deep-debug case.
 */
class WhatsAppCatalogException extends Exception
{
    protected array $context;

    public function __construct(string $message, int $code = 0, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function context(): array { return $this->context; }
}

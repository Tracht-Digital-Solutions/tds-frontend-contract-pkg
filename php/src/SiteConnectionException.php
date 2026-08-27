<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/** Stable cross-package error for connection UI HTTP mapping. */
class SiteConnectionException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly string $errorCode = 'invalid_connection',
    ) {
        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace TaskFlow\Core;

final class HttpException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly string $errorCode = 'http_error',
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace TaskFlow\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers = [],
        public readonly string $body = ''
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(mixed $payload, int $status = 200, array $headers = []): self
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            throw new \RuntimeException('Unable to encode the JSON response.');
        }

        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers, $json);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}

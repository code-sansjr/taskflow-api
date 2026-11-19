<?php

declare(strict_types=1);

namespace TaskFlow\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl = 3600
    ) {
        if (strlen($this->secret) < 32) {
            throw new \InvalidArgumentException('JWT_SECRET must contain at least 32 characters.');
        }

        if ($this->ttl < 60) {
            throw new \InvalidArgumentException('JWT_TTL must be at least 60 seconds.');
        }
    }

    /**
     * @return array{token: string, expires_at: int, expires_in: int}
     */
    public function issue(int $userId): array
    {
        $now = time();
        $expiresAt = $now + $this->ttl;

        $token = JWT::encode([
            'iss' => 'taskflow-api',
            'sub' => (string) $userId,
            'iat' => $now,
            'exp' => $expiresAt,
        ], $this->secret, 'HS256');

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_in' => $this->ttl,
        ];
    }

    public function userId(string $token): int
    {
        $payload = JWT::decode($token, new Key($this->secret, 'HS256'));
        $subject = $payload->sub ?? null;

        if (
            !is_int($subject)
            && !(is_string($subject) && ctype_digit($subject))
        ) {
            throw new \UnexpectedValueException('The token subject is invalid.');
        }

        $userId = (int) $subject;

        if ($userId <= 0) {
            throw new \UnexpectedValueException('The token subject is invalid.');
        }

        return $userId;
    }
}

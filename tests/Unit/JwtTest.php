<?php

declare(strict_types=1);

namespace TaskFlow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TaskFlow\Auth\JwtService;

final class JwtTest extends TestCase
{
    public function testIssuesAndReadsToken(): void
    {
        $jwt = new JwtService('testing-secret-key-with-at-least-32-chars', 3600);
        $issued = $jwt->issue(10);

        self::assertSame(10, $jwt->userId($issued['token']));
        self::assertSame(3600, $issued['expires_in']);
        self::assertSame(3, count(explode('.', $issued['token'])));
    }

    public function testRejectsTamperedToken(): void
    {
        $jwt = new JwtService('testing-secret-key-with-at-least-32-chars', 3600);
        $token = $jwt->issue(10)['token'];

        $this->expectException(\Throwable::class);
        $jwt->userId($token . 'tampered');
    }

    public function testRejectsShortSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new JwtService('too-short');
    }
}

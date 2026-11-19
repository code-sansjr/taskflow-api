<?php

declare(strict_types=1);

namespace TaskFlow\Auth;

use TaskFlow\Core\HttpException;
use TaskFlow\Core\Request;

final class Auth
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function userId(Request $request): int
    {
        $token = $request->bearerToken();

        if ($token === null) {
            throw new HttpException(401, 'Authentication required.', 'unauthorized');
        }

        try {
            return $this->jwt->userId($token);
        } catch (\Throwable) {
            throw new HttpException(401, 'Invalid or expired token.', 'unauthorized');
        }
    }
}

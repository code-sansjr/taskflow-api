<?php

declare(strict_types=1);

namespace TaskFlow\Tests\Feature;

use TaskFlow\Tests\TestCase;

final class AuthTest extends TestCase
{
    public function testUserCanRegister(): void
    {
        $response = $this->request('POST', '/api/auth/register', [
            'name' => 'Sandro',
            'email' => 'sandro@example.com',
            'password' => 'password123',
        ]);

        self::assertSame(201, $response->status, $response->body);

        $payload = $this->json($response);

        self::assertSame('Sandro', $payload['data']['user']['name']);
        self::assertSame('sandro@example.com', $payload['data']['user']['email']);
        self::assertArrayNotHasKey('password_hash', $payload['data']['user']);
        self::assertNotEmpty($payload['data']['token']);
        self::assertSame('Bearer', $payload['data']['token_type']);
    }

    public function testEmailMustBeUnique(): void
    {
        $this->register();

        $response = $this->request('POST', '/api/auth/register', [
            'name' => 'Outro Sandro',
            'email' => 'sandro@example.com',
            'password' => 'password123',
        ]);

        self::assertSame(422, $response->status, $response->body);

        $payload = $this->json($response);

        self::assertSame('validation_error', $payload['error']['code']);
        self::assertArrayHasKey('email', $payload['error']['details']['fields']);
    }

    public function testUserCanLogin(): void
    {
        $this->register();

        $response = $this->request('POST', '/api/auth/login', [
            'email' => 'sandro@example.com',
            'password' => 'password123',
        ]);

        self::assertSame(200, $response->status, $response->body);

        $payload = $this->json($response);

        self::assertSame('sandro@example.com', $payload['data']['user']['email']);
        self::assertNotEmpty($payload['data']['token']);
    }

    public function testLoginRejectsWrongPassword(): void
    {
        $this->register();

        $response = $this->request('POST', '/api/auth/login', [
            'email' => 'sandro@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertSame(401, $response->status, $response->body);
        self::assertSame('invalid_credentials', $this->json($response)['error']['code']);
    }

    public function testUserCanReadOwnProfile(): void
    {
        $auth = $this->register();
        $response = $this->request('GET', '/api/me', [], $auth['token']);

        self::assertSame(200, $response->status, $response->body);
        self::assertSame('sandro@example.com', $this->json($response)['data']['email']);
    }

    public function testProfileRequiresAuthentication(): void
    {
        $response = $this->request('GET', '/api/me');

        self::assertSame(401, $response->status, $response->body);
        self::assertSame('unauthorized', $this->json($response)['error']['code']);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $response = $this->request('GET', '/api/me', [], 'not-a-valid-token');

        self::assertSame(401, $response->status, $response->body);
    }
}

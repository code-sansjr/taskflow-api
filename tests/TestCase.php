<?php

declare(strict_types=1);

namespace TaskFlow\Tests;

use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use TaskFlow\App;
use TaskFlow\Core\Database;
use TaskFlow\Core\Request;
use TaskFlow\Core\Response;

abstract class TestCase extends BaseTestCase
{
    protected PDO $pdo;
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = Database::connect();
        $this->pdo->beginTransaction();

        $this->app = new App($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    protected function request(
        string $method,
        string $path,
        array $body = [],
        ?string $token = null,
        array $query = []
    ): Response {
        $headers = ['content-type' => 'application/json'];

        if ($token !== null) {
            $headers['authorization'] = 'Bearer ' . $token;
        }

        return $this->app->handle(new Request(strtoupper($method), $path, $query, $headers, $body));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        $decoded = json_decode($response->body, true);
        self::assertIsArray($decoded, $response->body);

        return $decoded;
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    protected function register(
        string $name = 'Sandro',
        string $email = 'sandro@example.com',
        string $password = 'password123'
    ): array {
        $response = $this->request('POST', '/api/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        self::assertSame(201, $response->status, $response->body);
        $payload = $this->json($response);

        return [
            'token' => $payload['data']['token'],
            'user' => $payload['data']['user'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function createProject(string $token, string $name = 'Projeto pessoal'): array
    {
        $response = $this->request('POST', '/api/projects', [
            'name' => $name,
            'description' => 'Projeto criado nos testes.',
        ], $token);

        self::assertSame(201, $response->status, $response->body);

        return $this->json($response)['data'];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    protected function createTask(string $token, int $projectId, array $body = []): array
    {
        $response = $this->request(
            'POST',
            "/api/projects/{$projectId}/tasks",
            $body + ['title' => 'Tarefa de teste'],
            $token
        );

        self::assertSame(201, $response->status, $response->body);

        return $this->json($response)['data'];
    }
}

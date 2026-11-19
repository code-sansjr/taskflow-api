<?php

declare(strict_types=1);

namespace TaskFlow\Tests\Feature;

use TaskFlow\Tests\TestCase;

final class ProjectTest extends TestCase
{
    public function testUserCanCreateListAndShowProject(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);

        self::assertSame(0, $project['tasks_total']);
        self::assertSame(0, $project['tasks_done']);
        self::assertSame(0, $project['progress']);

        $list = $this->json($this->request('GET', '/api/projects', [], $auth['token']));

        self::assertCount(1, $list['data']);
        self::assertSame(1, $list['meta']['total']);
        self::assertSame('Projeto pessoal', $list['data'][0]['name']);

        $show = $this->request('GET', "/api/projects/{$project['id']}", [], $auth['token']);

        self::assertSame(200, $show->status, $show->body);
        self::assertSame($project['id'], $this->json($show)['data']['id']);
    }

    public function testProjectNameIsRequired(): void
    {
        $auth = $this->register();

        $response = $this->request('POST', '/api/projects', [
            'description' => 'Sem nome',
        ], $auth['token']);

        self::assertSame(422, $response->status, $response->body);
        self::assertArrayHasKey('name', $this->json($response)['error']['details']['fields']);
    }

    public function testUserCanUpdateProject(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);

        $response = $this->request('PATCH', "/api/projects/{$project['id']}", [
            'name' => 'Projeto atualizado',
            'description' => null,
        ], $auth['token']);

        self::assertSame(200, $response->status, $response->body);

        $updated = $this->json($response)['data'];

        self::assertSame('Projeto atualizado', $updated['name']);
        self::assertNull($updated['description']);
    }

    public function testUserCanDeleteProject(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);

        $response = $this->request('DELETE', "/api/projects/{$project['id']}", [], $auth['token']);

        self::assertSame(204, $response->status, $response->body);
        self::assertSame(404, $this->request('GET', "/api/projects/{$project['id']}", [], $auth['token'])->status);
    }

    public function testUserCannotAccessAnotherUsersProject(): void
    {
        $owner = $this->register();
        $project = $this->createProject($owner['token']);
        $intruder = $this->register('Intruso', 'intruso@example.com');

        self::assertSame(
            404,
            $this->request('GET', "/api/projects/{$project['id']}", [], $intruder['token'])->status
        );
        self::assertSame(
            404,
            $this->request('PATCH', "/api/projects/{$project['id']}", ['name' => 'Invadido'], $intruder['token'])->status
        );
        self::assertSame(
            404,
            $this->request('DELETE', "/api/projects/{$project['id']}", [], $intruder['token'])->status
        );
        self::assertSame(
            200,
            $this->request('GET', "/api/projects/{$project['id']}", [], $owner['token'])->status
        );
    }

    public function testProjectsArePaginated(): void
    {
        $auth = $this->register();

        foreach (['Um', 'Dois', 'Tres'] as $name) {
            $this->createProject($auth['token'], $name);
        }

        $firstPage = $this->json($this->request(
            'GET',
            '/api/projects',
            [],
            $auth['token'],
            ['page' => 1, 'per_page' => 2]
        ));

        self::assertCount(2, $firstPage['data']);
        self::assertSame(3, $firstPage['meta']['total']);
        self::assertSame(2, $firstPage['meta']['last_page']);

        $secondPage = $this->json($this->request(
            'GET',
            '/api/projects',
            [],
            $auth['token'],
            ['page' => 2, 'per_page' => 2]
        ));

        self::assertCount(1, $secondPage['data']);
    }

    public function testProjectRoutesRequireAuthentication(): void
    {
        self::assertSame(401, $this->request('GET', '/api/projects')->status);
        self::assertSame(401, $this->request('POST', '/api/projects', ['name' => 'X'])->status);
    }
}

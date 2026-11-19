<?php

declare(strict_types=1);

namespace TaskFlow\Tests\Feature;

use TaskFlow\Tests\TestCase;

final class DashboardTest extends TestCase
{
    public function testDashboardSummarizesProjectsAndTasks(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);

        $this->createTask($auth['token'], $project['id'], [
            'title' => 'Atrasada',
            'status' => 'todo',
            'due_date' => date('Y-m-d', strtotime('-1 day')),
        ]);
        $this->createTask($auth['token'], $project['id'], [
            'title' => 'Para hoje',
            'status' => 'doing',
            'due_date' => date('Y-m-d'),
        ]);
        $this->createTask($auth['token'], $project['id'], [
            'title' => 'Concluída hoje',
            'status' => 'done',
            'due_date' => date('Y-m-d'),
        ]);
        $this->createTask($auth['token'], $project['id'], [
            'title' => 'Concluída sem prazo',
            'status' => 'done',
        ]);

        $response = $this->request('GET', '/api/dashboard', [], $auth['token']);
        self::assertSame(200, $response->status, $response->body);

        $dashboard = $this->json($response)['data'];

        self::assertSame(1, $dashboard['projects']);
        self::assertSame(4, $dashboard['tasks']['total']);
        self::assertSame(1, $dashboard['tasks']['todo']);
        self::assertSame(1, $dashboard['tasks']['doing']);
        self::assertSame(2, $dashboard['tasks']['done']);
        self::assertSame(1, $dashboard['tasks']['overdue']);
        self::assertSame(1, $dashboard['tasks']['due_today']);
        self::assertSame(50.0, $dashboard['completion_rate']);
    }

    public function testEmptyDashboardReturnsZeros(): void
    {
        $auth = $this->register();
        $dashboard = $this->json($this->request('GET', '/api/dashboard', [], $auth['token']))['data'];

        self::assertSame(0, $dashboard['projects']);
        self::assertSame(0, $dashboard['tasks']['total']);
        self::assertSame(0.0, $dashboard['completion_rate']);
    }

    public function testDashboardRequiresAuthentication(): void
    {
        self::assertSame(401, $this->request('GET', '/api/dashboard')->status);
    }
}

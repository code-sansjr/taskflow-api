<?php

declare(strict_types=1);

namespace TaskFlow\Tests\Feature;

use TaskFlow\Tests\TestCase;

final class TaskTest extends TestCase
{
    public function testUserCanCreateTask(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);
        $dueDate = date('Y-m-d', strtotime('-1 day'));

        $task = $this->createTask($auth['token'], $project['id'], [
            'title' => 'Estudar PHP',
            'description' => 'Revisar PDO e prepared statements.',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => $dueDate,
        ]);

        self::assertSame('Estudar PHP', $task['title']);
        self::assertSame('todo', $task['status']);
        self::assertSame('high', $task['priority']);
        self::assertSame($dueDate, $task['due_date']);
        self::assertNull($task['completed_at']);
        self::assertSame($project['id'], $task['project_id']);
        self::assertSame('Projeto pessoal', $task['project_name']);

        $updatedProject = $this->json(
            $this->request('GET', "/api/projects/{$project['id']}", [], $auth['token'])
        )['data'];

        self::assertSame(1, $updatedProject['tasks_total']);
        self::assertSame(1, $updatedProject['tasks_pending']);
        self::assertSame(0, $updatedProject['progress']);
    }

    public function testTaskValidationRejectsInvalidPayloads(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);
        $path = "/api/projects/{$project['id']}/tasks";

        $missingTitle = $this->request('POST', $path, ['description' => 'Sem título'], $auth['token']);
        self::assertSame(422, $missingTitle->status, $missingTitle->body);

        $invalidStatus = $this->request('POST', $path, [
            'title' => 'Tarefa',
            'status' => 'archived',
        ], $auth['token']);
        self::assertSame(422, $invalidStatus->status, $invalidStatus->body);

        $invalidDate = $this->request('POST', $path, [
            'title' => 'Tarefa',
            'due_date' => '2026-02-30',
        ], $auth['token']);
        self::assertSame(422, $invalidDate->status, $invalidDate->body);
    }

    public function testStatusTransitionsUpdateCompletedAt(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);
        $task = $this->createTask($auth['token'], $project['id']);

        $doing = $this->json($this->request(
            'PATCH',
            "/api/tasks/{$task['id']}",
            ['status' => 'doing'],
            $auth['token']
        ))['data'];

        self::assertSame('doing', $doing['status']);
        self::assertNull($doing['completed_at']);

        $done = $this->json($this->request(
            'PATCH',
            "/api/tasks/{$task['id']}",
            ['status' => 'done'],
            $auth['token']
        ))['data'];

        self::assertSame('done', $done['status']);
        self::assertNotNull($done['completed_at']);

        $reopened = $this->json($this->request(
            'PATCH',
            "/api/tasks/{$task['id']}",
            ['status' => 'todo'],
            $auth['token']
        ))['data'];

        self::assertSame('todo', $reopened['status']);
        self::assertNull($reopened['completed_at']);
    }

    public function testTaskFiltersAndPagination(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);
        $path = "/api/projects/{$project['id']}/tasks";

        $this->createTask($auth['token'], $project['id'], [
            'title' => 'Estudar PHP',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => date('Y-m-d', strtotime('-1 day')),
        ]);
        $this->createTask($auth['token'], $project['id'], [
            'title' => 'Revisar SQL',
            'status' => 'done',
            'priority' => 'low',
            'due_date' => date('Y-m-d'),
        ]);
        $this->createTask($auth['token'], $project['id'], [
            'title' => 'Planejar dashboard',
            'status' => 'doing',
            'priority' => 'medium',
        ]);

        $todo = $this->json($this->request('GET', $path, [], $auth['token'], ['status' => 'todo']));
        self::assertSame(1, $todo['meta']['total']);
        self::assertSame('Estudar PHP', $todo['data'][0]['title']);

        $low = $this->json($this->request('GET', $path, [], $auth['token'], ['priority' => 'low']));
        self::assertSame(1, $low['meta']['total']);
        self::assertSame('Revisar SQL', $low['data'][0]['title']);

        $overdue = $this->json($this->request('GET', $path, [], $auth['token'], ['overdue' => '1']));
        self::assertSame(1, $overdue['meta']['total']);
        self::assertSame('Estudar PHP', $overdue['data'][0]['title']);

        $search = $this->json($this->request('GET', $path, [], $auth['token'], ['search' => 'SQL']));
        self::assertSame(1, $search['meta']['total']);
        self::assertSame('Revisar SQL', $search['data'][0]['title']);

        $page = $this->json($this->request('GET', $path, [], $auth['token'], [
            'page' => 1,
            'per_page' => 2,
        ]));
        self::assertCount(2, $page['data']);
        self::assertSame(3, $page['meta']['total']);
        self::assertSame(2, $page['meta']['last_page']);
    }

    public function testTasksAreScopedToTheirProjects(): void
    {
        $auth = $this->register();
        $first = $this->createProject($auth['token'], 'Primeiro');
        $second = $this->createProject($auth['token'], 'Segundo');

        $this->createTask($auth['token'], $first['id'], ['title' => 'Tarefa do primeiro']);
        $this->createTask($auth['token'], $second['id'], ['title' => 'Tarefa do segundo']);

        $firstTasks = $this->json($this->request(
            'GET',
            "/api/projects/{$first['id']}/tasks",
            [],
            $auth['token']
        ));

        self::assertSame(1, $firstTasks['meta']['total']);
        self::assertSame('Tarefa do primeiro', $firstTasks['data'][0]['title']);

        $secondTasks = $this->json($this->request(
            'GET',
            "/api/projects/{$second['id']}/tasks",
            [],
            $auth['token']
        ));

        self::assertSame(1, $secondTasks['meta']['total']);
        self::assertSame('Tarefa do segundo', $secondTasks['data'][0]['title']);
    }

    public function testUserCannotAccessAnotherUsersTask(): void
    {
        $owner = $this->register();
        $project = $this->createProject($owner['token']);
        $task = $this->createTask($owner['token'], $project['id']);
        $intruder = $this->register('Intruso', 'intruso@example.com');

        self::assertSame(
            404,
            $this->request('GET', "/api/tasks/{$task['id']}", [], $intruder['token'])->status
        );
        self::assertSame(
            404,
            $this->request('PATCH', "/api/tasks/{$task['id']}", ['status' => 'done'], $intruder['token'])->status
        );
        self::assertSame(
            404,
            $this->request('DELETE', "/api/tasks/{$task['id']}", [], $intruder['token'])->status
        );
        self::assertSame(
            404,
            $this->request('GET', "/api/projects/{$project['id']}/tasks", [], $intruder['token'])->status
        );
    }

    public function testUserCanDeleteTask(): void
    {
        $auth = $this->register();
        $project = $this->createProject($auth['token']);
        $task = $this->createTask($auth['token'], $project['id']);

        $response = $this->request('DELETE', "/api/tasks/{$task['id']}", [], $auth['token']);

        self::assertSame(204, $response->status, $response->body);
        self::assertSame(
            404,
            $this->request('GET', "/api/tasks/{$task['id']}", [], $auth['token'])->status
        );
    }

    public function testProjectMustExistForTaskCreation(): void
    {
        $auth = $this->register();

        $response = $this->request('POST', '/api/projects/9999/tasks', [
            'title' => 'Tarefa órfã',
        ], $auth['token']);

        self::assertSame(404, $response->status, $response->body);
        self::assertSame('project_not_found', $this->json($response)['error']['code']);
    }

    public function testTaskRoutesRequireAuthentication(): void
    {
        self::assertSame(401, $this->request('GET', '/api/projects/1/tasks')->status);
        self::assertSame(401, $this->request('GET', '/api/tasks/1')->status);
    }
}

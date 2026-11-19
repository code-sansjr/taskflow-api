<?php

declare(strict_types=1);

namespace TaskFlow\Controllers;

use TaskFlow\Auth\Auth;
use TaskFlow\Core\HttpException;
use TaskFlow\Core\Pagination;
use TaskFlow\Core\Request;
use TaskFlow\Core\Response;
use TaskFlow\Core\Validator;
use TaskFlow\Repositories\ProjectRepository;
use TaskFlow\Repositories\TaskRepository;

final class TaskController
{
    private const STATUSES = ['todo', 'doing', 'done'];
    private const PRIORITIES = ['low', 'medium', 'high'];

    public function __construct(
        private readonly Auth $auth,
        private readonly ProjectRepository $projects,
        private readonly TaskRepository $tasks
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);
        $projectId = $this->projectId($params);
        $this->assertProjectOwnership($projectId, $userId);

        $filters = $this->filters($request->query);
        [$page, $perPage] = Pagination::fromQuery($request->query);
        $result = $this->tasks->allByProject($projectId, $userId, $filters, $page, $perPage);

        return Response::json([
            'data' => $result['items'],
            'meta' => Pagination::meta($page, $perPage, $result['total']),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);
        $projectId = $this->projectId($params);
        $this->assertProjectOwnership($projectId, $userId);

        $data = Validator::validate($request->body, [
            'title' => 'required|string|min:2|max:180',
            'description' => 'nullable|string|max:5000',
            'status' => 'nullable|string|in:todo,doing,done',
            'priority' => 'nullable|string|in:low,medium,high',
            'due_date' => 'nullable|date',
        ]);

        $title = trim((string) $data['title']);

        if (mb_strlen($title) < 2) {
            throw $this->validationError('title', 'The title must be at least 2 characters.');
        }

        $status = is_string($data['status'] ?? null) ? $data['status'] : 'todo';
        $priority = is_string($data['priority'] ?? null) ? $data['priority'] : 'medium';

        $task = $this->tasks->create($projectId, $userId, [
            'title' => $title,
            'description' => $this->normalizeDescription($data['description'] ?? null),
            'status' => $status,
            'priority' => $priority,
            'due_date' => $data['due_date'] ?? null,
            'completed_at' => $status === 'done' ? date('Y-m-d H:i:s') : null,
        ]);

        return Response::json(['data' => $task], 201);
    }

    public function show(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);
        $task = $this->tasks->findForUser($this->taskId($params), $userId);

        if ($task === null) {
            throw new HttpException(404, 'Task not found.', 'task_not_found');
        }

        return Response::json(['data' => $task]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);
        $taskId = $this->taskId($params);
        $task = $this->tasks->findForUser($taskId, $userId);

        if ($task === null) {
            throw new HttpException(404, 'Task not found.', 'task_not_found');
        }

        $data = Validator::validate($request->body, [
            'title' => 'sometimes|required|string|min:2|max:180',
            'description' => 'sometimes|nullable|string|max:5000',
            'status' => 'sometimes|required|string|in:todo,doing,done',
            'priority' => 'sometimes|required|string|in:low,medium,high',
            'due_date' => 'sometimes|nullable|date',
        ]);

        if ($data === []) {
            throw new HttpException(
                422,
                'Validation failed.',
                'validation_error',
                ['fields' => ['body' => ['At least one field must be provided.']]]
            );
        }

        if (array_key_exists('title', $data)) {
            $data['title'] = trim((string) $data['title']);

            if (mb_strlen($data['title']) < 2) {
                throw $this->validationError('title', 'The title must be at least 2 characters.');
            }
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->normalizeDescription($data['description']);
        }

        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'done') {
                $data['completed_at'] = $task['completed_at'] ?? date('Y-m-d H:i:s');
            } else {
                $data['completed_at'] = null;
            }
        }

        $updatedTask = $this->tasks->update($taskId, $userId, $data);

        if ($updatedTask === null) {
            throw new HttpException(404, 'Task not found.', 'task_not_found');
        }

        return Response::json(['data' => $updatedTask]);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);

        if (!$this->tasks->delete($this->taskId($params), $userId)) {
            throw new HttpException(404, 'Task not found.', 'task_not_found');
        }

        return Response::noContent();
    }

    private function assertProjectOwnership(int $projectId, int $userId): void
    {
        if (!$this->projects->existsForUser($projectId, $userId)) {
            throw new HttpException(404, 'Project not found.', 'project_not_found');
        }
    }

    private function projectId(array $params): int
    {
        $id = filter_var(
            $params['project_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            throw new HttpException(404, 'Project not found.', 'project_not_found');
        }

        return (int) $id;
    }

    private function taskId(array $params): int
    {
        $id = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            throw new HttpException(404, 'Task not found.', 'task_not_found');
        }

        return (int) $id;
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status?: string, priority?: string, overdue?: bool, search?: string}
     */
    private function filters(array $query): array
    {
        $filters = [];

        if (isset($query['status']) && $query['status'] !== '') {
            if (!is_string($query['status']) || !in_array($query['status'], self::STATUSES, true)) {
                throw $this->validationError('status', 'The status must be one of: todo, doing, done.');
            }

            $filters['status'] = $query['status'];
        }

        if (isset($query['priority']) && $query['priority'] !== '') {
            if (!is_string($query['priority']) || !in_array($query['priority'], self::PRIORITIES, true)) {
                throw $this->validationError('priority', 'The priority must be one of: low, medium, high.');
            }

            $filters['priority'] = $query['priority'];
        }

        if (isset($query['overdue']) && is_string($query['overdue'])) {
            if (in_array(strtolower($query['overdue']), ['1', 'true', 'yes'], true)) {
                $filters['overdue'] = true;
            }
        }

        if (isset($query['search']) && is_string($query['search']) && trim($query['search']) !== '') {
            $search = trim($query['search']);

            if (mb_strlen($search) > 100) {
                throw $this->validationError('search', 'The search term may not be greater than 100 characters.');
            }

            $filters['search'] = $search;
        }

        return $filters;
    }

    private function normalizeDescription(mixed $description): ?string
    {
        if (!is_string($description)) {
            return null;
        }

        $description = trim($description);

        return $description === '' ? null : $description;
    }

    private function validationError(string $field, string $message): HttpException
    {
        return new HttpException(
            422,
            'Validation failed.',
            'validation_error',
            ['fields' => [$field => [$message]]]
        );
    }
}

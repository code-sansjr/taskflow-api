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

final class ProjectController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly ProjectRepository $projects
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->userId($request);
        [$page, $perPage] = Pagination::fromQuery($request->query);

        $projects = $this->projects->allByUser($userId, $page, $perPage);
        $total = $this->projects->countByUser($userId);

        return Response::json([
            'data' => $projects,
            'meta' => Pagination::meta($page, $perPage, $total),
        ]);
    }

    public function store(Request $request): Response
    {
        $userId = $this->auth->userId($request);
        $data = Validator::validate($request->body, [
            'name' => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:2000',
        ]);

        $name = trim((string) $data['name']);

        if (mb_strlen($name) < 2) {
            throw $this->validationError('name', 'The name must be at least 2 characters.');
        }

        $description = $this->normalizeDescription($data['description'] ?? null);
        $project = $this->projects->create($userId, $name, $description);

        return Response::json(['data' => $project], 201);
    }

    public function show(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);
        $project = $this->projects->findForUser($this->routeId($params), $userId);

        if ($project === null) {
            throw new HttpException(404, 'Project not found.', 'project_not_found');
        }

        return Response::json(['data' => $project]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);
        $projectId = $this->routeId($params);

        if ($this->projects->findForUser($projectId, $userId) === null) {
            throw new HttpException(404, 'Project not found.', 'project_not_found');
        }

        $data = Validator::validate($request->body, [
            'name' => 'sometimes|required|string|min:2|max:120',
            'description' => 'sometimes|nullable|string|max:2000',
        ]);

        if ($data === []) {
            throw new HttpException(
                422,
                'Validation failed.',
                'validation_error',
                ['fields' => ['body' => ['At least one field must be provided.']]]
            );
        }

        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);

            if (mb_strlen($data['name']) < 2) {
                throw $this->validationError('name', 'The name must be at least 2 characters.');
            }
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->normalizeDescription($data['description']);
        }

        $project = $this->projects->update($projectId, $userId, $data);

        return Response::json(['data' => $project]);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $userId = $this->auth->userId($request);
        $projectId = $this->routeId($params);

        if (!$this->projects->delete($projectId, $userId)) {
            throw new HttpException(404, 'Project not found.', 'project_not_found');
        }

        return Response::noContent();
    }

    private function routeId(array $params): int
    {
        $id = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            throw new HttpException(404, 'Project not found.', 'project_not_found');
        }

        return (int) $id;
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

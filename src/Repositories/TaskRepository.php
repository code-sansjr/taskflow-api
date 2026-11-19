<?php

declare(strict_types=1);

namespace TaskFlow\Repositories;

use PDO;

final class TaskRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{status?: string, priority?: string, overdue?: bool, search?: string} $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function allByProject(
        int $projectId,
        int $userId,
        array $filters,
        int $page,
        int $perPage
    ): array {
        $where = [
            't.project_id = :project_id',
            'p.user_id = :user_id',
        ];
        $params = [
            ':project_id' => $projectId,
            ':user_id' => $userId,
        ];

        if (isset($filters['status'])) {
            $where[] = 't.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['priority'])) {
            $where[] = 't.priority = :priority';
            $params[':priority'] = $filters['priority'];
        }

        if (($filters['overdue'] ?? false) === true) {
            $where[] = "t.status <> 'done' AND t.due_date IS NOT NULL AND t.due_date < :today";
            $params[':today'] = date('Y-m-d');
        }

        if (isset($filters['search'])) {
            $where[] = 't.title LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             WHERE {$whereSql}"
        );
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT t.id, t.project_id, t.title, t.description, t.status, t.priority,
                    t.due_date, t.completed_at, t.created_at, t.updated_at
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             WHERE {$whereSql}
             ORDER BY (t.due_date IS NULL) ASC, t.due_date ASC, t.id DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => array_map(
                fn (array $task): array => $this->castTask($task),
                $statement->fetchAll()
            ),
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.project_id, t.title, t.description, t.status, t.priority,
                    t.due_date, t.completed_at, t.created_at, t.updated_at,
                    p.name AS project_name
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             WHERE t.id = :id AND p.user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        $task = $statement->fetch();

        return $task === false ? null : $this->castTask($task);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(int $projectId, int $userId, array $data): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tasks
                (project_id, title, description, status, priority, due_date, completed_at)
             VALUES
                (:project_id, :title, :description, :status, :priority, :due_date, :completed_at)'
        );
        $statement->execute([
            ':project_id' => $projectId,
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':status' => $data['status'],
            ':priority' => $data['priority'],
            ':due_date' => $data['due_date'],
            ':completed_at' => $data['completed_at'],
        ]);

        $task = $this->findForUser((int) $this->pdo->lastInsertId(), $userId);

        if ($task === null) {
            throw new \RuntimeException('The task could not be loaded after creation.');
        }

        return $task;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    public function update(int $id, int $userId, array $fields): ?array
    {
        $allowed = ['title', 'description', 'status', 'priority', 'due_date', 'completed_at'];
        $sets = [];
        $params = [
            ':id' => $id,
            ':user_id' => $userId,
        ];

        foreach ($fields as $field => $value) {
            if (in_array($field, $allowed, true)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
        }

        if ($sets !== []) {
            $statement = $this->pdo->prepare(
                'UPDATE tasks SET ' . implode(', ', $sets) . '
                 WHERE id = :id
                   AND project_id IN (SELECT id FROM projects WHERE user_id = :user_id)'
            );
            $statement->execute($params);
        }

        return $this->findForUser($id, $userId);
    }

    public function delete(int $id, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE t
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             WHERE t.id = :id AND p.user_id = :user_id'
        );
        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return array{total: int, todo: int, doing: int, done: int, overdue: int, due_today: int}
     */
    public function summaryForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(t.status = 'todo'), 0) AS todo,
                    COALESCE(SUM(t.status = 'doing'), 0) AS doing,
                    COALESCE(SUM(t.status = 'done'), 0) AS done,
                    COALESCE(SUM(t.status <> 'done' AND t.due_date IS NOT NULL AND t.due_date < :today_overdue), 0) AS overdue,
                    COALESCE(SUM(t.status <> 'done' AND t.due_date = :today_due), 0) AS due_today
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             WHERE p.user_id = :user_id"
        );
        $today = date('Y-m-d');
        $statement->execute([
            ':user_id' => $userId,
            ':today_overdue' => $today,
            ':today_due' => $today,
        ]);

        $summary = $statement->fetch() ?: [];

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'todo' => (int) ($summary['todo'] ?? 0),
            'doing' => (int) ($summary['doing'] ?? 0),
            'done' => (int) ($summary['done'] ?? 0),
            'overdue' => (int) ($summary['overdue'] ?? 0),
            'due_today' => (int) ($summary['due_today'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function castTask(array $task): array
    {
        $task['id'] = (int) $task['id'];
        $task['project_id'] = (int) $task['project_id'];

        return $task;
    }
}

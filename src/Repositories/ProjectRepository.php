<?php

declare(strict_types=1);

namespace TaskFlow\Repositories;

use PDO;

final class ProjectRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allByUser(int $userId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $statement = $this->pdo->prepare(
            "SELECT p.id, p.name, p.description, p.created_at, p.updated_at,
                    COUNT(t.id) AS tasks_total,
                    COALESCE(SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END), 0) AS tasks_done
             FROM projects p
             LEFT JOIN tasks t ON t.project_id = p.id
             WHERE p.user_id = :user_id
             GROUP BY p.id, p.name, p.description, p.created_at, p.updated_at
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT :limit OFFSET :offset"
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $project): array => $this->castProject($project),
            $statement->fetchAll()
        );
    }

    public function countByUser(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM projects WHERE user_id = :user_id');
        $statement->execute([':user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT p.id, p.name, p.description, p.created_at, p.updated_at,
                    COUNT(t.id) AS tasks_total,
                    COALESCE(SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END), 0) AS tasks_done
             FROM projects p
             LEFT JOIN tasks t ON t.project_id = p.id
             WHERE p.id = :id AND p.user_id = :user_id
             GROUP BY p.id, p.name, p.description, p.created_at, p.updated_at
             LIMIT 1"
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();

        $project = $statement->fetch();

        return $project === false ? null : $this->castProject($project);
    }

    public function existsForUser(int $id, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM projects WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(int $userId, string $name, ?string $description): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO projects (user_id, name, description) VALUES (:user_id, :name, :description)'
        );
        $statement->execute([
            ':user_id' => $userId,
            ':name' => $name,
            ':description' => $description,
        ]);

        $project = $this->findForUser((int) $this->pdo->lastInsertId(), $userId);

        if ($project === null) {
            throw new \RuntimeException('The project could not be loaded after creation.');
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    public function update(int $id, int $userId, array $fields): ?array
    {
        $allowed = ['name', 'description'];
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
                'UPDATE projects SET ' . implode(', ', $sets) . ' WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute($params);
        }

        return $this->findForUser($id, $userId);
    }

    public function delete(int $id, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM projects WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    private function castProject(array $project): array
    {
        $project['id'] = (int) $project['id'];
        $project['tasks_total'] = (int) $project['tasks_total'];
        $project['tasks_done'] = (int) $project['tasks_done'];
        $project['tasks_pending'] = $project['tasks_total'] - $project['tasks_done'];
        $project['progress'] = $project['tasks_total'] > 0
            ? (int) round(($project['tasks_done'] / $project['tasks_total']) * 100)
            : 0;

        return $project;
    }
}

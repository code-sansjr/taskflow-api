<?php

declare(strict_types=1);

namespace TaskFlow\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, string $email, string $passwordHash): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
        );
        $statement->execute([
            ':name' => $name,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);

        $user = $this->findById((int) $this->pdo->lastInsertId());

        if ($user === null) {
            throw new \RuntimeException('The user could not be loaded after creation.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, created_at, updated_at FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        $user = $statement->fetch();

        if ($user === false) {
            return null;
        }

        $user['id'] = (int) $user['id'];

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => $email]);

        $user = $statement->fetch();

        if ($user === false) {
            return null;
        }

        $user['id'] = (int) $user['id'];

        return $user;
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => $email]);

        return $statement->fetchColumn() !== false;
    }
}

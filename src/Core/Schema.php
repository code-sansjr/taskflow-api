<?php

declare(strict_types=1);

namespace TaskFlow\Core;

use PDO;

final class Schema
{
    public static function migrate(PDO $pdo, string $file): void
    {
        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new \RuntimeException("Unable to read the schema file: {$file}");
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace TaskFlow\Core;

final class Pagination
{
    /**
     * @param array<string, mixed> $query
     * @return array{0: int, 1: int}
     */
    public static function fromQuery(array $query): array
    {
        $page = max(1, self::toInt($query['page'] ?? 1, 1));
        $perPage = self::toInt($query['per_page'] ?? 20, 20);
        $perPage = min(100, max(1, $perPage));

        return [$page, $perPage];
    }

    /**
     * @return array{page: int, per_page: int, total: int, last_page: int}
     */
    public static function meta(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private static function toInt(mixed $value, int $fallback): int
    {
        if (!is_scalar($value)) {
            return $fallback;
        }

        return (int) $value;
    }
}

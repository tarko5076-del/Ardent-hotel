<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class FavoriteRepository
{
    private PDO $pdo;

    public function __construct(private readonly FoodRepository $foods)
    {
        $this->pdo = Database::connection();
    }

    public function listByUserId(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT f.*, fi.created_at AS favorited_at FROM favorite_items fi INNER JOIN foods f ON f.id = fi.food_id WHERE fi.user_id = ? ORDER BY fi.created_at DESC'
        );
        $statement->execute([$userId]);

        return array_map(function (array $favorite): array {
            $normalized = $this->foods->normalize($favorite);
            $normalized['favorited_at'] = $favorite['favorited_at'] ?? null;
            return $normalized;
        }, $statement->fetchAll());
    }

    public function add(int $userId, int|string $foodId): void
    {
        $statement = $this->pdo->prepare('INSERT IGNORE INTO favorite_items (user_id, food_id) VALUES (?, ?)');
        $statement->execute([$userId, $foodId]);
    }

    public function remove(int $userId, int|string $foodId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM favorite_items WHERE user_id = ? AND food_id = ?');
        $statement->execute([$userId, $foodId]);
    }
}

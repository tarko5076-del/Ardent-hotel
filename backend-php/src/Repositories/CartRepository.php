<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class CartRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function getByUserId(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.food_id AS itemId, c.quantity, f.name, f.price, f.image, f.category, f.stock_quantity FROM cart_items c INNER JOIN foods f ON f.id = c.food_id WHERE c.user_id = ? ORDER BY c.created_at DESC'
        );
        $statement->execute([$userId]);

        return array_map(fn (array $item) => $this->normalize($item), $statement->fetchAll());
    }

    public function findItem(int $userId, int|string $foodId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM cart_items WHERE user_id = ? AND food_id = ? LIMIT 1');
        $statement->execute([$userId, $foodId]);
        $item = $statement->fetch();

        return $item ?: null;
    }

    public function setQuantity(int $userId, int|string $foodId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->deleteItem($userId, $foodId);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO cart_items (user_id, food_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $statement->execute([$userId, $foodId, $quantity]);
    }

    public function deleteItem(int $userId, int|string $foodId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND food_id = ?');
        $statement->execute([$userId, $foodId]);
    }

    public function clearByUserId(int $userId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
        $statement->execute([$userId]);
    }

    public function buildResponse(array $items): array
    {
        $cartData = [];
        $subtotal = 0.0;
        $totalItems = 0;

        foreach ($items as $item) {
            $cartData[$item['itemId']] = $item['quantity'];
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
            $totalItems += (int) $item['quantity'];
        }

        return [
            'cartItems' => $items,
            'cartData' => $cartData,
            'subtotal' => $subtotal,
            'totalItems' => $totalItems,
        ];
    }

    private function normalize(array $item): array
    {
        $quantity = (int) ($item['quantity'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        $itemId = (string) ($item['itemId'] ?? $item['food_id']);

        return [
            ...$item,
            'itemId' => $itemId,
            'id' => $itemId,
            '_id' => $itemId,
            'price' => $price,
            'quantity' => $quantity,
            'stock_quantity' => (int) ($item['stock_quantity'] ?? 0),
            'lineTotal' => $price * $quantity,
        ];
    }
}

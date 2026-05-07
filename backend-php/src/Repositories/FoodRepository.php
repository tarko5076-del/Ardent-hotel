<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

final class FoodRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function normalize(?array $food): ?array
    {
        if ($food === null) {
            return null;
        }

        $stockQuantity = (int) ($food['stock_quantity'] ?? 0);

        return [
            ...$food,
            'id' => (int) $food['id'],
            '_id' => (string) $food['id'],
            'price' => (float) $food['price'],
            'stock_quantity' => $stockQuantity,
            'isAvailable' => $stockQuantity > 0,
        ];
    }

    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = 'category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR description LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        if (($filters['availableOnly'] ?? null) === 'true') {
            $where[] = 'stock_quantity > 0';
        }

        $sql = 'SELECT * FROM foods';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY created_at DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $food) => $this->normalize($food), $statement->fetchAll());
    }

    public function findById(int|string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM foods WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $food = $statement->fetch();

        return $this->normalize($food ?: null);
    }

    public function findByIdForUpdate(PDO $connection, int|string $id): ?array
    {
        $statement = $connection->prepare(
            'SELECT id, name, description, price, image, category, stock_quantity, created_at FROM foods WHERE id = ? FOR UPDATE'
        );
        $statement->execute([$id]);
        $food = $statement->fetch();

        return $this->normalize($food ?: null);
    }

    public function create(array $payload): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO foods (name, description, price, image, category, stock_quantity) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $payload['name'],
            $payload['description'],
            $payload['price'],
            $payload['image'],
            $payload['category'],
            $payload['stockQuantity'],
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Unable to create food.');
    }

    public function update(int|string $id, array $payload): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE foods SET name = ?, description = ?, price = ?, image = ?, category = ?, stock_quantity = ? WHERE id = ?'
        );
        $statement->execute([
            $payload['name'],
            $payload['description'],
            $payload['price'],
            $payload['image'],
            $payload['category'],
            $payload['stockQuantity'],
            $id,
        ]);

        return $this->findById($id) ?? throw new \RuntimeException('Unable to update food.');
    }

    public function updateStock(int|string $id, int $stockQuantity): array
    {
        $statement = $this->pdo->prepare('UPDATE foods SET stock_quantity = ? WHERE id = ?');
        $statement->execute([$stockQuantity, $id]);

        return $this->findById($id) ?? throw new \RuntimeException('Unable to update stock.');
    }

    public function delete(int|string $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM foods WHERE id = ?');
        $statement->execute([$id]);
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Support\Helpers;
use PDO;

final class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function mapUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            '_id' => (string) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'defaultAddress' => Helpers::parseJsonValue($user['default_address'] ?? null, null),
            'role' => $user['role'] ?? 'user',
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
        ];
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        return $user ? $this->mapUserRecord($user) : null;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $user = $statement->fetch();

        return $user ? $this->mapUserRecord($user) : null;
    }

    public function create(array $payload): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, phone, role, default_address) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $payload['name'],
            $payload['email'],
            $payload['password'],
            $payload['phone'] ?? '',
            $payload['role'] ?? 'user',
            isset($payload['defaultAddress']) ? json_encode($payload['defaultAddress'], JSON_THROW_ON_ERROR) : null,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Unable to create user.');
    }

    public function updateProfile(int $id, array $payload): array
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET name = ?, phone = ?, default_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $statement->execute([
            $payload['name'],
            $payload['phone'] ?? '',
            isset($payload['defaultAddress']) ? json_encode($payload['defaultAddress'], JSON_THROW_ON_ERROR) : null,
            $id,
        ]);

        return $this->findById($id) ?? throw new \RuntimeException('Unable to update user.');
    }

    private function mapUserRecord(array $user): array
    {
        $mapped = $this->mapUser($user);
        $mapped['password'] = $user['password'];
        return $mapped;
    }
}

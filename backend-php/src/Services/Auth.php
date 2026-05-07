<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\HttpException;
use App\Http\Request;
use App\Repositories\UserRepository;
use App\Support\Jwt;

final class Auth
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function user(Request $request): array
    {
        $authorization = (string) $request->header('authorization', '');
        $token = null;

        if (str_starts_with($authorization, 'Bearer ')) {
            $token = trim(substr($authorization, 7));
        }

        if ($token === null || $token === '') {
            $token = (string) $request->header('token', '');
        }

        if ($token === '') {
            throw new HttpException('Not authorized. Please log in again.', 401);
        }

        $payload = Jwt::decode($token);
        $user = $this->users->findById((int) $payload['id']);

        if ($user === null) {
            throw new HttpException('Account not found. Please log in again.', 401);
        }

        return $user;
    }

    public function requireRole(array $user, string ...$roles): void
    {
        if (!in_array($user['role'] ?? 'user', $roles, true)) {
            throw new HttpException('You do not have permission to perform this action.', 403);
        }
    }
}

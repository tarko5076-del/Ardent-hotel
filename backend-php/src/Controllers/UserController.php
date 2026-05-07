<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Repositories\UserRepository;
use App\Services\Auth;
use App\Support\Jwt;

final class UserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Auth $auth
    ) {
    }

    public function health(Request $request): void
    {
        JsonResponse::send([
            'success' => true,
            'message' => 'Food delivery API is running.',
        ]);
    }

    public function login(Request $request): void
    {
        $email = strtolower(trim((string) $request->body('email', '')));
        $password = (string) $request->body('password', '');

        if ($email === '' || $password === '') {
            throw new HttpException('Email and password are required.', 400);
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw new HttpException('User does not exist.', 404);
        }

        if (!password_verify($password, $user['password'])) {
            throw new HttpException('Invalid credentials.', 401);
        }

        JsonResponse::send([
            'success' => true,
            'message' => 'Login successful.',
            'token' => Jwt::create($user),
            'user' => $this->publicUser($user),
        ]);
    }

    public function register(Request $request): void
    {
        $name = trim((string) $request->body('name', ''));
        $email = strtolower(trim((string) $request->body('email', '')));
        $password = (string) $request->body('password', '');
        $phone = trim((string) $request->body('phone', ''));

        if ($name === '' || $email === '' || $password === '') {
            throw new HttpException('Name, email, and password are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException('Please enter a valid email address.', 400);
        }

        if (!$this->passwordIsStrongEnough($password)) {
            throw new HttpException('Password must be at least 8 characters and include letters and numbers.', 400);
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new HttpException('A user with that email already exists.', 409);
        }

        $user = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'phone' => $phone,
        ]);

        JsonResponse::send([
            'success' => true,
            'message' => 'Registration successful.',
            'token' => Jwt::create($user),
            'user' => $this->publicUser($user),
        ], 201);
    }

    public function me(Request $request): void
    {
        JsonResponse::send([
            'success' => true,
            'user' => $this->publicUser($this->auth->user($request)),
        ]);
    }

    public function updateMe(Request $request): void
    {
        $user = $this->auth->user($request);
        $name = trim((string) $request->body('name', ''));
        $phone = trim((string) $request->body('phone', ''));
        $defaultAddress = $this->sanitizeDefaultAddress($request->body('defaultAddress'));

        if ($name === '') {
            throw new HttpException('Name is required.', 400);
        }

        $updatedUser = $this->users->updateProfile((int) $user['id'], [
            'name' => $name,
            'phone' => $phone,
            'defaultAddress' => $defaultAddress,
        ]);

        JsonResponse::send([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => $this->publicUser($updatedUser),
        ]);
    }

    private function publicUser(array $user): array
    {
        return $this->users->mapUser($user);
    }

    private function passwordIsStrongEnough(string $password): bool
    {
        return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
    }

    private function sanitizeDefaultAddress(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $address = [
            'firstName' => trim((string) ($value['firstName'] ?? '')),
            'lastName' => trim((string) ($value['lastName'] ?? '')),
            'email' => trim((string) ($value['email'] ?? '')),
            'street' => trim((string) ($value['street'] ?? '')),
            'city' => trim((string) ($value['city'] ?? '')),
            'state' => trim((string) ($value['state'] ?? '')),
            'zipcode' => trim((string) ($value['zipcode'] ?? '')),
            'country' => trim((string) ($value['country'] ?? '')),
            'phone' => trim((string) ($value['phone'] ?? '')),
        ];

        $hasAnyValue = false;
        foreach ($address as $field) {
            if ($field !== '') {
                $hasAnyValue = true;
                break;
            }
        }

        if (!$hasAnyValue) {
            return null;
        }

        if ($address['email'] !== '' && !filter_var($address['email'], FILTER_VALIDATE_EMAIL)) {
            throw new HttpException('Please enter a valid email address for the saved delivery details.', 400);
        }

        return $address;
    }
}

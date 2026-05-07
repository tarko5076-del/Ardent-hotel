<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Env;
use App\Http\HttpException;

final class Jwt
{
    public static function create(array $user): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $issuedAt = time();
        $payload = [
            'id' => $user['id'],
            'role' => $user['role'] ?? 'user',
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::durationInSeconds(Env::get('JWT_EXPIRES_IN', '7d')),
        ];

        $segments = [
            self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), self::secret(), true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new HttpException('Invalid or expired session. Please log in again.', 401);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, self::secret(), true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw new HttpException('Invalid or expired session. Please log in again.', 401);
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);

        if (!is_array($payload) || !isset($payload['id'])) {
            throw new HttpException('Invalid or expired session. Please log in again.', 401);
        }

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new HttpException('Invalid or expired session. Please log in again.', 401);
        }

        return $payload;
    }

    private static function durationInSeconds(?string $value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 7 * 24 * 60 * 60;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (!preg_match('/^(\d+)([smhd])$/i', $value, $matches)) {
            return 7 * 24 * 60 * 60;
        }

        $amount = (int) $matches[1];
        $unit = strtolower($matches[2]);

        return match ($unit) {
            's' => $amount,
            'm' => $amount * 60,
            'h' => $amount * 60 * 60,
            'd' => $amount * 24 * 60 * 60,
            default => 7 * 24 * 60 * 60,
        };
    }

    private static function secret(): string
    {
        return (string) Env::get('JWT_SECRET', 'development-secret-change-me');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}

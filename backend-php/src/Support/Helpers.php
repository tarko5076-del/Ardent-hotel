<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Env;

final class Helpers
{
    public const FOOD_CATEGORIES = [
        'Salad',
        'Rolls',
        'Deserts',
        'Sandwich',
        'Cake',
        'Pure Veg',
        'Pasta',
        'Noodles',
    ];

    public static function parseJsonValue(mixed $value, mixed $fallback = null): mixed
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public static function uploadsDirectory(): string
    {
        $uploadsDirectory = Env::get('UPLOADS_DIR', 'storage/uploads');

        if (!str_starts_with($uploadsDirectory, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $uploadsDirectory)) {
            return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $uploadsDirectory;
        }

        return $uploadsDirectory;
    }

    public static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower((string) $value), ['false', '0', ''], true);
    }

    public static function formatDateValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            return substr($value, 0, 10);
        }

        return date('Y-m-d', strtotime((string) $value));
    }
}

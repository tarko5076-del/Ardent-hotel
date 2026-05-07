<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    private array $routeParams = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $headers,
        private readonly string $rawBody = ''
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $headers = self::captureHeaders();

        $rawInput = file_get_contents('php://input') ?: '';
        [$body, $files] = self::parseBodyAndFiles($method, $headers, $rawInput);

        return new self($method, $path, $_GET, $body, $files, $headers, $rawInput);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function body(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $normalized = strtolower($key);
        return $this->headers[$normalized] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function withRouteParams(array $params): self
    {
        $clone = clone $this;
        $clone->routeParams = $params;
        return $clone;
    }

    private static function captureHeaders(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }

        return $normalized;
    }

    private static function parseBodyAndFiles(string $method, array $headers, string $rawInput): array
    {
        $contentTypeHeader = strtolower((string) ($headers['content-type'] ?? ''));
        $contentType = trim(explode(';', $contentTypeHeader, 2)[0]);

        if ($method === 'GET' || $method === 'HEAD') {
            return [[], []];
        }

        $body = [];
        $files = self::normalizeFiles($_FILES);

        if ($contentType === 'application/json') {
            $decoded = json_decode($rawInput, true);
            $body = is_array($decoded) ? $decoded : [];
            return [$body, $files];
        }

        if ($contentType === 'application/x-www-form-urlencoded') {
            if ($method === 'POST') {
                return [$_POST, $files];
            }

            parse_str($rawInput, $body);
            return [$body, $files];
        }

        if ($contentType === 'multipart/form-data') {
            if ($method === 'POST') {
                return [$_POST, $files];
            }

            return self::parseMultipartFormData($rawInput, $contentTypeHeader);
        }

        if ($method === 'POST') {
            return [$_POST, $files];
        }

        return [[], $files];
    }

    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (is_array($file['name'] ?? null)) {
                continue;
            }

            $normalized[$key] = $file;
        }

        return $normalized;
    }

    private static function parseMultipartFormData(string $rawInput, string $contentTypeHeader): array
    {
        if (!preg_match('/boundary=(.*)$/', $contentTypeHeader, $matches)) {
            return [[], []];
        }

        $boundary = trim($matches[1], '"');
        $parts = explode('--' . $boundary, $rawInput);
        $fields = [];
        $files = [];

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");

            if ($part === '' || $part === '--' || $part === "--\r\n") {
                continue;
            }

            [$rawHeaders, $content] = array_pad(explode("\r\n\r\n", $part, 2), 2, null);

            if ($rawHeaders === null || $content === null) {
                continue;
            }

            if (str_ends_with($content, "\r\n")) {
                $content = substr($content, 0, -2);
            }

            $headers = [];
            foreach (explode("\r\n", $rawHeaders) as $headerLine) {
                if (!str_contains($headerLine, ':')) {
                    continue;
                }

                [$headerName, $headerValue] = explode(':', $headerLine, 2);
                $headers[strtolower(trim($headerName))] = trim($headerValue);
            }

            $disposition = $headers['content-disposition'] ?? '';

            if (!preg_match('/name="([^"]+)"/', $disposition, $nameMatch)) {
                continue;
            }

            $fieldName = $nameMatch[1];

            if (preg_match('/filename="([^"]*)"/', $disposition, $fileMatch)) {
                $filename = $fileMatch[1];

                if ($filename === '') {
                    continue;
                }

                $tmpPath = tempnam(sys_get_temp_dir(), 'upload_');
                file_put_contents($tmpPath, $content);

                $files[$fieldName] = [
                    'name' => $filename,
                    'type' => $headers['content-type'] ?? 'application/octet-stream',
                    'tmp_name' => $tmpPath,
                    'error' => 0,
                    'size' => filesize($tmpPath),
                ];

                continue;
            }

            $fields[$fieldName] = $content;
        }

        return [$fields, $files];
    }
}

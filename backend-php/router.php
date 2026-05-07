<?php

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$serverRootDir = __DIR__;
$projectRootDir = dirname(__DIR__);
$frontendDistDir = $projectRootDir . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'dist';
$adminDistDir = $projectRootDir . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'dist';
$uploadsDirectory = getenv('UPLOADS_DIR') ?: ($serverRootDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads');

if (!str_starts_with($uploadsDirectory, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $uploadsDirectory)) {
    $uploadsDirectory = $serverRootDir . DIRECTORY_SEPARATOR . $uploadsDirectory;
}

$mimeTypes = [
    'css' => 'text/css; charset=utf-8',
    'gif' => 'image/gif',
    'html' => 'text/html; charset=utf-8',
    'jpeg' => 'image/jpeg',
    'jpg' => 'image/jpeg',
    'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'png' => 'image/png',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
];

$serveFile = static function (string $path) use ($mimeTypes): bool {
    if (!is_file($path)) {
        return false;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    return true;
};

$resolveSafePath = static function (string $baseDir, string $relativePath): ?string {
    $baseRealPath = realpath($baseDir);

    if ($baseRealPath === false) {
        return null;
    }

    $normalizedRelativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    $candidatePath = $baseRealPath . DIRECTORY_SEPARATOR . $normalizedRelativePath;

    if (is_file($candidatePath)) {
        return $candidatePath;
    }

    $candidateRealPath = realpath($candidatePath);

    if ($candidateRealPath === false || !str_starts_with($candidateRealPath, $baseRealPath)) {
        return null;
    }

    return $candidateRealPath;
};

if (str_starts_with($uriPath, '/api/')) {
    require $serverRootDir . '/public/index.php';
    return true;
}

if (str_starts_with($uriPath, '/images/')) {
    $filename = basename($uriPath);
    $imagePath = $uploadsDirectory . DIRECTORY_SEPARATOR . $filename;

    if ($serveFile($imagePath)) {
        return true;
    }
}

if ($uriPath === '/admin' || $uriPath === '/admin/') {
    $serveFile($adminDistDir . DIRECTORY_SEPARATOR . 'index.html');
    return true;
}

if (str_starts_with($uriPath, '/admin/')) {
    $adminRelativePath = substr($uriPath, strlen('/admin/'));
    $adminAssetPath = $resolveSafePath($adminDistDir, $adminRelativePath);

    if ($adminAssetPath !== null && $serveFile($adminAssetPath)) {
        return true;
    }

    $serveFile($adminDistDir . DIRECTORY_SEPARATOR . 'index.html');
    return true;
}

$frontendRelativePath = $uriPath === '/' ? 'index.html' : ltrim($uriPath, '/');
$frontendAssetPath = $resolveSafePath($frontendDistDir, $frontendRelativePath);

if ($frontendAssetPath !== null && $serveFile($frontendAssetPath)) {
    return true;
}

$serveFile($frontendDistDir . DIRECTORY_SEPARATOR . 'index.html');
return true;

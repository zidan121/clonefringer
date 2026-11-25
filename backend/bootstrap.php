<?php
declare(strict_types=1);

/**
 * Shared API bootstrap. Keep credentials in environment variables, never in Git.
 * PHP 8.1+ with PDO MySQL is required.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function jsonResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('CLONEFRINGER_DB_HOST') ?: '127.0.0.1';
    $port = getenv('CLONEFRINGER_DB_PORT') ?: '3306';
    $name = getenv('CLONEFRINGER_DB_NAME') ?: 'clonefringer';
    $user = getenv('CLONEFRINGER_DB_USER') ?: '';
    $pass = getenv('CLONEFRINGER_DB_PASS') ?: '';

    if ($user === '') {
        throw new RuntimeException('Database credentials are not configured.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

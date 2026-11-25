<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if ($method === 'GET' && ($path === '/api/health' || $path === '/health')) {
    try {
        $pdo = database();
        $pdo->query('SELECT 1');
        jsonResponse(200, [
            'ok' => true,
            'application' => 'CLONEFRINGER',
            'database' => 'connected',
            'time' => date(DATE_ATOM),
        ]);
    } catch (Throwable $error) {
        error_log('[CLONEFRINGER health] ' . $error->getMessage());
        jsonResponse(503, [
            'ok' => false,
            'application' => 'CLONEFRINGER',
            'database' => 'unavailable',
            'message' => 'Database belum terhubung atau konfigurasi belum lengkap.',
        ]);
    }
}

jsonResponse(404, [
    'ok' => false,
    'message' => 'Endpoint tidak ditemukan.',
]);

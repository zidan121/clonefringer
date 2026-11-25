<?php
declare(strict_types=1);

// Router for PHP's built-in development server.
// Production web servers should rewrite /api/* to api.php.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/' && $path !== '/api/health' && $path !== '/health') {
    return false;
}
require __DIR__ . '/api.php';

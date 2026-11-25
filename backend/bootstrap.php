<?php
declare(strict_types=1);

/** Shared API bootstrap. Credentials must be supplied via environment variables. */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

function jsonResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = getenv('CLONEFRINGER_DB_HOST') ?: '127.0.0.1';
    $port = getenv('CLONEFRINGER_DB_PORT') ?: '3306';
    $name = getenv('CLONEFRINGER_DB_NAME') ?: 'clonefringer';
    $user = getenv('CLONEFRINGER_DB_USER') ?: '';
    $pass = getenv('CLONEFRINGER_DB_PASS') ?: '';
    if ($user === '') throw new RuntimeException('Database credentials are not configured.');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('clonefringer_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) jsonResponse(400, ['ok'=>false,'message'=>'Format JSON tidak valid.']);
    return $data;
}

function currentUser(): ?array
{
    startAppSession();
    return $_SESSION['user'] ?? null;
}

function requireUser(): array
{
    $user = currentUser();
    if (!$user) jsonResponse(401, ['ok'=>false,'message'=>'Silakan login terlebih dahulu.']);
    return $user;
}

function requireRole(array $roles): array
{
    $user = requireUser();
    if (!in_array($user['role'], $roles, true)) {
        jsonResponse(403, ['ok'=>false,'message'=>'Anda tidak memiliki izin untuk tindakan ini.']);
    }
    return $user;
}

function requireCsrf(): void
{
    startAppSession();
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!$known || !$sent || !hash_equals($known, $sent)) {
        jsonResponse(419, ['ok'=>false,'message'=>'Token keamanan tidak valid. Muat ulang sesi dan coba lagi.']);
    }
}

function audit(PDO $pdo, ?int $userId, string $action, string $type, ?int $entityId, ?array $before, ?array $after): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,before_data,after_data,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $userId, $action, $type, $entityId,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
        substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

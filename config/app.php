<?php
declare(strict_types=1);

session_start();

date_default_timezone_set('Africa/Nairobi');

const APP_NAME = 'NiaRide';
const APP_CURRENCY = 'KSh';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'niaride';
const DB_USER = 'root';
const DB_PASS = '';
const GOOGLE_MAPS_API_KEY = '';

function db(): PDO
{
    static $connection;
    if (!$connection) {
        $connection = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $connection;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ?page=login');
        exit;
    }
    return $user;
}

function json_response(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

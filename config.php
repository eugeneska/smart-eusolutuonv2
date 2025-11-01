<?php
declare(strict_types=1);
const DB_DSN  = 'mysql:host=db;port=3306;dbname=demo_contact;charset=utf8mb4';
const DB_USER = 'app';
const DB_PASS = 'app';
function pdo(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

// --- Admin auth config ---
const ADMIN_USER = 'admin';
const ADMIN_PASS_HASH = '$2y$10$9bWihdGxV6tKJq6X7q9mEe3mINwUiaFh8YlHkK3JxPZ1o6dP2c0q2'; 
// это hash от 'sesadmin123' (password_hash)

// --- session + csrf ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function isAdmin(): bool {
    return !empty($_SESSION['admin_logged_in']);
}
function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function checkCsrf(?string $token): void {
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
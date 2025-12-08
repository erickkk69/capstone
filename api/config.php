<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}


const DB_HOST = '127.0.0.1';  
const DB_PORT = '3306';
const DB_NAME = 'capstone_db';  
const DB_USER = 'root'; 
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Database connection failed', 'detail' => $e->getMessage()]);
        exit;
    }
    return $pdo;
}

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, email, role, barangay, created_at, archived, last_activity FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && isset($user['archived']) && $user['archived'] == 1) {
            session_destroy();
            return null;
        }
        
        return $user ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }
    return $u;
}

function require_abc_role(): array {
    $u = require_login();
    if (($u['role'] ?? '') !== 'ABC') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Forbidden: ABC only']);
        exit;
    }
    return $u;
}

?>

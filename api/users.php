<?php
// api/users.php - List/Create/Delete users (ABC Secretary only)

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        handle_get();
        break;
    case 'POST':
        handle_post();
        break;
    case 'DELETE':
        handle_delete();
        break;
    default:
        json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

function handle_get(): void {
    require_abc_role();
    $pdo = get_pdo();
    $stmt = $pdo->query('SELECT id, email, role, barangay, created_at FROM users ORDER BY role DESC, created_at ASC');
    $users = $stmt->fetchAll();
    json_response(['ok' => true, 'users' => $users]);
}

function handle_post(): void {
    require_abc_role();
    $pdo = get_pdo();
    $body = read_json_body();
    $email = strtolower(trim($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $barangay = trim((string)($body['barangay'] ?? ''));
    $role = trim((string)($body['role'] ?? 'Barangay Secretary'));

    if ($email === '' || $password === '' || $barangay === '') {
        json_response(['ok' => false, 'error' => 'Missing required fields'], 400);
    }

    // Enforce minimum password length
    if (strlen($password) < 8) {
        json_response(['ok' => false, 'error' => 'Password must be at least 8 characters long'], 400);
    }

    // Check duplicate email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Email already exists'], 409);
    }

    // Allow multiple users per barangay (remove unique-per-barangay restriction)

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (email, password_hash, role, barangay) VALUES (?, ?, ?, ?)');
    $ins->execute([$email, $hash, $role, $barangay]);

    json_response(['ok' => true]);
}

function handle_delete(): void {
    require_abc_role();
    $pdo = get_pdo();

    // Accept either id or email via query string
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $email = isset($_GET['email']) ? strtolower(trim((string)$_GET['email'])) : '';
    if ($id <= 0 && $email === '') {
        json_response(['ok' => false, 'error' => 'Provide id or email'], 400);
    }

    // Do not allow deleting ABC Secretary
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
    } else {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
    $user = $stmt->fetch();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'User not found'], 404);
    }
    if ($user['role'] === 'ABC Secretary') {
        json_response(['ok' => false, 'error' => 'Cannot delete ABC Secretary'], 403);
    }

    if ($email !== '') {
        $del = $pdo->prepare('DELETE FROM users WHERE email = ?');
        $del->execute([$email]);
    } else {
        $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$id]);
    }

    json_response(['ok' => true]);
}

?>

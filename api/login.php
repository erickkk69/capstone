<?php
// api/login.php

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$body = read_json_body();
$email = strtolower(trim($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['ok' => false, 'error' => 'Email and password are required'], 400);
}

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT id, email, password_hash, role, barangay FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['ok' => false, 'error' => 'Invalid email or password'], 401);
}

// Set session
$_SESSION['user_id'] = (int)$user['id'];

json_response([
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'barangay' => $user['barangay'],
    ],
]);

?>

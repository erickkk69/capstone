<?php
// api/change_password.php - Handle password changes from forgot.html

declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$body = read_json_body();
$email = strtolower(trim($body['email'] ?? ''));
$new_password = trim($body['new_password'] ?? '');

if ($email === '' || $new_password === '') {
    json_response(['ok' => false, 'error' => 'Email and new password are required'], 400);
}

// Enforce minimum password length
if (strlen($new_password) < 8) {
    json_response(['ok' => false, 'error' => 'Password must be at least 8 characters long'], 400);
}

$pdo = get_pdo();

// Check if user exists and is not ABC
$stmt = $pdo->prepare('SELECT id, role, barangay FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['ok' => false, 'error' => 'User not found'], 404);
}

// Don't allow ABC password change
if ($user['role'] === 'ABC') {
    json_response(['ok' => false, 'error' => 'ABC password cannot be changed via this system'], 403);
}

// Check if user already has a pending reset request
$check_stmt = $pdo->prepare('SELECT id FROM password_reset_requests WHERE user_id = ? AND status = "pending"');
$check_stmt->execute([$user['id']]);
if ($check_stmt->fetch()) {
    json_response(['ok' => false, 'error' => 'You already have a pending password reset request. Please wait for admin approval.'], 400);
}

// Begin transaction
$pdo->beginTransaction();

try {
    // Hash the new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Get IP and user agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Create password reset request instead of directly changing password
    $req_stmt = $pdo->prepare('
        INSERT INTO password_reset_requests 
        (user_id, user_email, user_role, user_barangay, new_password_hash, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $req_stmt->execute([
        $user['id'], 
        $email, 
        $user['role'], 
        $user['barangay'],
        $password_hash,
        $ip_address, 
        $user_agent
    ]);
    
    // Lock the user account until admin approves
    $lock_stmt = $pdo->prepare('UPDATE users SET account_locked = 1 WHERE id = ?');
    $lock_stmt->execute([$user['id']]);
    
    $pdo->commit();
    
    json_response([
        'ok' => true,
        'message' => 'Password reset request submitted successfully. Your account is temporarily locked. An admin will review your request soon.'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'error' => 'Failed to submit password reset request: ' . $e->getMessage()], 500);
}
?>
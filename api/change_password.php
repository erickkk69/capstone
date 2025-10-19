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

// Check if user exists and is not ABC Secretary
$stmt = $pdo->prepare('SELECT id, role, barangay FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['ok' => false, 'error' => 'User not found'], 404);
}

// Don't allow ABC Secretary password change
if ($user['role'] === 'ABC Secretary') {
    json_response(['ok' => false, 'error' => 'ABC Secretary password cannot be changed via this system'], 403);
}

// Create password change logs table if it doesn't exist
$pdo->exec('
    CREATE TABLE IF NOT EXISTS password_change_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        user_role VARCHAR(64) NOT NULL,
        user_barangay VARCHAR(128) NOT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

// Begin transaction
$pdo->beginTransaction();

try {
    // Update user password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$password_hash, $user['id']]);
    
    // Log the password change
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $log_stmt = $pdo->prepare('
        INSERT INTO password_change_logs (user_id, user_email, user_role, user_barangay, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $log_stmt->execute([
        $user['id'], 
        $email, 
        $user['role'], 
        $user['barangay'], 
        $ip_address, 
        $user_agent
    ]);
    
    $pdo->commit();
    
    json_response([
        'ok' => true,
        'message' => 'Password has been successfully changed'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'error' => 'Failed to change password'], 500);
}
?>
<?php
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

$attemptStmt = $pdo->prepare('SELECT failed_attempts, lockout_count, locked_until FROM login_attempts WHERE email = ?');
$attemptStmt->execute([$email]);
$attemptData = $attemptStmt->fetch();

if ($attemptData && $attemptData['locked_until']) {
    $lockedUntil = new DateTime($attemptData['locked_until']);
    $now = new DateTime();
    
    if ($now < $lockedUntil) {
        $remainingSeconds = $lockedUntil->getTimestamp() - $now->getTimestamp();
        $minutes = floor($remainingSeconds / 60);
        $seconds = $remainingSeconds % 60;
        
        $timeMsg = $minutes > 0 
            ? "{$minutes} minute" . ($minutes > 1 ? 's' : '') . " and {$seconds} second" . ($seconds != 1 ? 's' : '')
            : "{$seconds} second" . ($seconds != 1 ? 's' : '');
        
        json_response([
            'ok' => false, 
            'error' => "Account is temporarily locked due to multiple failed login attempts. Please try again in {$timeMsg}.",
            'error_code' => 'ACCOUNT_LOCKED_ATTEMPTS',
            'locked_until' => $attemptData['locked_until'],
            'remaining_seconds' => $remainingSeconds
        ], 403);
    } else {
        $clearLockStmt = $pdo->prepare('UPDATE login_attempts SET locked_until = NULL WHERE email = ?');
        $clearLockStmt->execute([$email]);
    }
}

$stmt = $pdo->prepare('SELECT id, email, password_hash, role, barangay, archived, account_locked FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['ok' => false, 'error' => 'This email is not registered in the system.', 'error_code' => 'EMAIL_NOT_REGISTERED'], 401);
}

if (isset($user['archived']) && $user['archived'] == 1) {
    json_response(['ok' => false, 'error' => 'This account has been archived and cannot be used. Please contact the administrator.', 'error_code' => 'ACCOUNT_ARCHIVED'], 403);
}

if (isset($user['account_locked']) && $user['account_locked'] == 1) {
    json_response(['ok' => false, 'error' => 'Your account is temporarily locked due to a pending password reset request. Please wait for admin approval.', 'error_code' => 'ACCOUNT_LOCKED'], 403);
}

if (!password_verify($password, $user['password_hash'])) {
    if ($attemptData) {
        $failedAttempts = (int)$attemptData['failed_attempts'] + 1;
        $lockoutCount = (int)$attemptData['lockout_count'];
        $lockedUntil = null;
        
        if ($failedAttempts >= 5) {
            $lockoutCount++;
            $lockoutSeconds = 30 * pow(2, $lockoutCount - 1);
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutSeconds);
            
            $updateStmt = $pdo->prepare('UPDATE login_attempts SET failed_attempts = ?, lockout_count = ?, locked_until = ? WHERE email = ?');
            $updateStmt->execute([$failedAttempts, $lockoutCount, $lockedUntil, $email]);
            
            $minutes = floor($lockoutSeconds / 60);
            $seconds = $lockoutSeconds % 60;
            $timeMsg = $minutes > 0 
                ? "{$minutes} minute" . ($minutes > 1 ? 's' : '') . ($seconds > 0 ? " and {$seconds} second" . ($seconds != 1 ? 's' : '') : '')
                : "{$seconds} second" . ($seconds != 1 ? 's' : '');
            
            json_response([
                'ok' => false, 
                'error' => "Too many failed login attempts. Your account has been locked for {$timeMsg}.",
                'error_code' => 'ACCOUNT_LOCKED_ATTEMPTS'
            ], 403);
        } else {
            $updateStmt = $pdo->prepare('UPDATE login_attempts SET failed_attempts = ?, locked_until = NULL WHERE email = ?');
            $updateStmt->execute([$failedAttempts, $email]);
        }
    } else {
        $insertStmt = $pdo->prepare('INSERT INTO login_attempts (email, failed_attempts, lockout_count) VALUES (?, 1, 0)');
        $insertStmt->execute([$email]);
    }
    
    json_response(['ok' => false, 'error' => 'Invalid email or password'], 401);
}

if ($attemptData) {
    $resetStmt = $pdo->prepare('UPDATE login_attempts SET failed_attempts = 0, lockout_count = 0, locked_until = NULL WHERE email = ?');
    $resetStmt->execute([$email]);
}

$activityStmt = $pdo->prepare('UPDATE users SET last_activity = NOW() WHERE id = ?');
$activityStmt->execute([$user['id']]);

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

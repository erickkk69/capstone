<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$current_user = current_user();
if (!$current_user) {
    json_response(['ok' => false, 'error' => 'Not authenticated'], 401);
}

if ($current_user['role'] !== 'ABC') {
    json_response(['ok' => false, 'error' => 'Access denied. Only ABC can approve/reject password reset requests.'], 403);
}

$body = read_json_body();
$request_id = (int)($body['request_id'] ?? 0);
$action = strtolower(trim($body['action'] ?? '')); // 'approve' or 'reject'
$rejection_reason = trim($body['rejection_reason'] ?? 'Request rejected by admin');

if ($request_id <= 0) {
    json_response(['ok' => false, 'error' => 'Invalid request ID'], 400);
}

if (!in_array($action, ['approve', 'reject'])) {
    json_response(['ok' => false, 'error' => 'Invalid action. Must be "approve" or "reject"'], 400);
}

$pdo = get_pdo();

$stmt = $pdo->prepare('
    SELECT id, user_id, user_email, new_password_hash, status 
    FROM password_reset_requests 
    WHERE id = ?
');
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    json_response(['ok' => false, 'error' => 'Reset request not found'], 404);
}

if ($request['status'] !== 'pending') {
    json_response(['ok' => false, 'error' => 'This request has already been ' . $request['status']], 400);
}

$pdo->beginTransaction();

try {
    if ($action === 'approve') {
        $update_stmt = $pdo->prepare('UPDATE users SET password_hash = ?, account_locked = 0 WHERE id = ?');
        $update_stmt->execute([$request['new_password_hash'], $request['user_id']]);
        
        $status_stmt = $pdo->prepare('
            UPDATE password_reset_requests 
            SET status = "approved", reviewed_at = NOW(), reviewed_by = ? 
            WHERE id = ?
        ');
        $status_stmt->execute([$current_user['id'], $request_id]);
        
        $log_stmt = $pdo->prepare('
            INSERT INTO password_change_logs (user_id, user_email, user_role, user_barangay, ip_address, user_agent) 
            SELECT user_id, user_email, user_role, user_barangay, ip_address, user_agent
            FROM password_reset_requests
            WHERE id = ?
        ');
        $log_stmt->execute([$request_id]);
        
        $message = 'Password reset request approved. User account unlocked and password updated.';
        
    } else {
        $status_stmt = $pdo->prepare('
            UPDATE password_reset_requests 
            SET status = "rejected", reviewed_at = NOW(), reviewed_by = ?, rejection_reason = ? 
            WHERE id = ?
        ');
        $status_stmt->execute([$current_user['id'], $rejection_reason, $request_id]);
        
        $unlock_stmt = $pdo->prepare('UPDATE users SET account_locked = 0 WHERE id = ?');
        $unlock_stmt->execute([$request['user_id']]);
        
        $message = 'Password reset request rejected. User account unlocked with original password retained.';
    }
    
    $pdo->commit();
    
    json_response([
        'ok' => true,
        'message' => $message,
        'action' => $action,
        'user_email' => $request['user_email']
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['ok' => false, 'error' => 'Failed to process request: ' . $e->getMessage()], 500);
}
?>

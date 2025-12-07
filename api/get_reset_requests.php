<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

$current_user = current_user();
if (!$current_user) {
    json_response(['ok' => false, 'error' => 'Not authenticated'], 401);
}

if ($current_user['role'] !== 'ABC') {
    json_response(['ok' => false, 'error' => 'Access denied. Only ABC can manage password reset requests.'], 403);
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $request_id = $_GET['id'] ?? null;
    
    if (!$request_id) {
        json_response(['ok' => false, 'error' => 'Request ID is required'], 400);
    }
    
    $pdo = get_pdo();
    
    try {
        // Get request details before deleting
        $stmt = $pdo->prepare('SELECT user_email FROM password_reset_requests WHERE id = ?');
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            json_response(['ok' => false, 'error' => 'Reset request not found'], 404);
        }
        
        // Delete the request
        $delete_stmt = $pdo->prepare('DELETE FROM password_reset_requests WHERE id = ?');
        $delete_stmt->execute([$request_id]);
        
        json_response([
            'ok' => true,
            'message' => 'Password reset request deleted successfully',
            'user_email' => $request['user_email']
        ]);
        
    } catch (Exception $e) {
        json_response(['ok' => false, 'error' => 'Failed to delete reset request: ' . $e->getMessage()], 500);
    }
    exit;
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$pdo = get_pdo();

$status = $_GET['status'] ?? 'pending';
$valid_statuses = ['pending', 'approved', 'rejected', 'all'];

if (!in_array($status, $valid_statuses)) {
    $status = 'pending';
}

try {
    if ($status === 'all') {
        $stmt = $pdo->prepare('
            SELECT 
                r.id,
                r.user_id,
                r.user_email,
                r.user_role,
                r.user_barangay,
                r.status,
                r.requested_at,
                r.reviewed_at,
                r.rejection_reason,
                r.ip_address,
                reviewer.email as reviewed_by_email,
                reviewer.role as reviewed_by_role
            FROM password_reset_requests r
            LEFT JOIN users reviewer ON r.reviewed_by = reviewer.id
            ORDER BY 
                CASE r.status 
                    WHEN "pending" THEN 1 
                    WHEN "approved" THEN 2 
                    WHEN "rejected" THEN 3 
                END,
                r.requested_at DESC
        ');
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare('
            SELECT 
                r.id,
                r.user_id,
                r.user_email,
                r.user_role,
                r.user_barangay,
                r.status,
                r.requested_at,
                r.reviewed_at,
                r.rejection_reason,
                r.ip_address,
                reviewer.email as reviewed_by_email,
                reviewer.role as reviewed_by_role
            FROM password_reset_requests r
            LEFT JOIN users reviewer ON r.reviewed_by = reviewer.id
            WHERE r.status = ?
            ORDER BY r.requested_at DESC
        ');
        $stmt->execute([$status]);
    }
    
    $requests = $stmt->fetchAll();
    
    $count_stmt = $pdo->query('SELECT COUNT(*) as count FROM password_reset_requests WHERE status = "pending"');
    $pending_count = $count_stmt->fetch()['count'];
    
    json_response([
        'ok' => true,
        'requests' => $requests,
        'pending_count' => (int)$pending_count,
        'filter' => $status
    ]);
    
} catch (Exception $e) {
    json_response(['ok' => false, 'error' => 'Failed to fetch reset requests: ' . $e->getMessage()], 500);
}
?>

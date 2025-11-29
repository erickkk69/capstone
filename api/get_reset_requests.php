<?php
// api/get_reset_requests.php - Get all password reset requests (Admin only)

declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

// Check if user is logged in and is ABC
$current_user = current_user();
if (!$current_user) {
    json_response(['ok' => false, 'error' => 'Not authenticated'], 401);
}

if ($current_user['role'] !== 'ABC') {
    json_response(['ok' => false, 'error' => 'Access denied. Only ABC can view password reset requests.'], 403);
}

$pdo = get_pdo();

// Get filter from query parameter (default: pending)
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
    
    // Get count of pending requests
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

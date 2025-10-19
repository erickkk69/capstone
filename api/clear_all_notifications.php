<?php
// api/clear_all_notifications.php - Clear all password change notifications

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

// Require ABC Secretary role
require_abc_role();

$pdo = get_pdo();

try {
    // Count notifications before deletion
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM password_change_logs');
    $count = $stmt->fetch()['count'];
    
    // Delete all notifications
    $pdo->exec('DELETE FROM password_change_logs');
    
    json_response([
        'ok' => true, 
        'message' => "Cleared $count password change notifications",
        'deleted_count' => $count
    ]);
    
} catch (Exception $e) {
    json_response(['ok' => false, 'error' => 'Failed to clear notifications'], 500);
}
?>
<?php
// api/delete_password_notification.php - Delete password change notification

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

// Require ABC role
require_abc_role();

$notification_id = $_GET['id'] ?? '';

if (!$notification_id || !is_numeric($notification_id)) {
    json_response(['ok' => false, 'error' => 'Valid notification ID is required'], 400);
}

$pdo = get_pdo();

try {
    // Check if notification exists
    $stmt = $pdo->prepare('SELECT id FROM password_change_logs WHERE id = ?');
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch();
    
    if (!$notification) {
        json_response(['ok' => false, 'error' => 'Notification not found'], 404);
    }
    
    // Delete the notification
    $stmt = $pdo->prepare('DELETE FROM password_change_logs WHERE id = ?');
    $stmt->execute([$notification_id]);
    
    json_response(['ok' => true, 'message' => 'Notification deleted successfully']);
    
} catch (Exception $e) {
    json_response(['ok' => false, 'error' => 'Failed to delete notification'], 500);
}
?>
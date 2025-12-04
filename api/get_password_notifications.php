<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

require_abc_role();

$pdo = get_pdo();

try {
    $stmt = $pdo->prepare('
        SELECT 
            pcl.*,
            CASE 
                WHEN pcl.changed_at > (NOW() - INTERVAL 1 DAY) THEN "new"
                WHEN pcl.changed_at > (NOW() - INTERVAL 7 DAY) THEN "recent" 
                ELSE "old"
            END as status
        FROM password_change_logs pcl 
        WHERE pcl.changed_at > (NOW() - INTERVAL 30 DAY)
        ORDER BY pcl.changed_at DESC
        LIMIT 50
    ');
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as unread_count 
        FROM password_change_logs 
        WHERE changed_at > (NOW() - INTERVAL 1 DAY)
    ');
    $stmt->execute();
    $unread_count = $stmt->fetch()['unread_count'];
    
    json_response([
        'ok' => true,
        'notifications' => $logs,
        'unread_count' => (int)$unread_count
    ]);
    
} catch (Exception $e) {
    json_response(['ok' => false, 'error' => 'Failed to fetch notifications'], 500);
}
?>
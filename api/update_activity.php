<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$user = require_login();

$pdo = get_pdo();

$stmt = $pdo->prepare('UPDATE users SET last_activity = NOW() WHERE id = ?');
$stmt->execute([$user['id']]);

json_response(['ok' => true, 'timestamp' => date('Y-m-d H:i:s')]);
?>

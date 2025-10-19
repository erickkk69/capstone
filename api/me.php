<?php
// api/me.php

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$u = current_user();
if (!$u) {
    json_response(['ok' => false, 'user' => null], 200);
}

json_response(['ok' => true, 'user' => $u]);

?>

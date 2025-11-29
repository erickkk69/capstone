<?php
// api/users.php - List/Create/Delete users (ABC only)

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';

allow_cors();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        handle_get();
        break;
    case 'POST':
        handle_post();
        break;
    case 'DELETE':
        handle_delete();
        break;
    case 'PATCH':
        handle_patch();
        break;
    default:
        json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

function handle_get(): void {
    require_abc_role();
    $pdo = get_pdo();
    
    // Check if we need to return archived users
    $archived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;
    
    $stmt = $pdo->prepare('SELECT id, email, role, barangay, created_at, archived FROM users WHERE archived = ? ORDER BY role DESC, created_at ASC');
    $stmt->execute([$archived]);
    $users = $stmt->fetchAll();
    json_response(['ok' => true, 'users' => $users]);
}

function handle_post(): void {
    require_abc_role();
    $pdo = get_pdo();
    $body = read_json_body();
    $email = strtolower(trim($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $barangay = trim((string)($body['barangay'] ?? ''));
    $role = trim((string)($body['role'] ?? 'Barangay Secretary'));

    if ($email === '' || $password === '' || $barangay === '') {
        json_response(['ok' => false, 'error' => 'Missing required fields'], 400);
    }

    // Enforce minimum password length
    if (strlen($password) < 8) {
        json_response(['ok' => false, 'error' => 'Password must be at least 8 characters long'], 400);
    }

    // Check duplicate email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Email already exists'], 409);
    }

    // Check if barangay already has a user (prevent duplicate barangay accounts)
    $barangayStmt = $pdo->prepare('SELECT id, email FROM users WHERE barangay = ? AND archived = 0');
    $barangayStmt->execute([$barangay]);
    $existingUser = $barangayStmt->fetch();
    if ($existingUser) {
        json_response(['ok' => false, 'error' => "A user account already exists for {$barangay} barangay (Email: {$existingUser['email']})"], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (email, password_hash, role, barangay) VALUES (?, ?, ?, ?)');
    $ins->execute([$email, $hash, $role, $barangay]);

    json_response(['ok' => true]);
}

function handle_delete(): void {
    require_abc_role();
    $pdo = get_pdo();

    // Accept either id or email via query string
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $email = isset($_GET['email']) ? strtolower(trim((string)$_GET['email'])) : '';
    if ($id <= 0 && $email === '') {
        json_response(['ok' => false, 'error' => 'Provide id or email'], 400);
    }

    // Do not allow deleting ABC
    try {
        if ($email !== '') {
            $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
        } else {
            $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
            $stmt->execute([$id]);
        }
        $user = $stmt->fetch();
        if (!$user) {
            json_response(['ok' => false, 'error' => 'User not found'], 404);
        }
        if (($user['role'] ?? '') === 'ABC') {
            json_response(['ok' => false, 'error' => 'Cannot delete ABC'], 403);
        }

        // Check if this is a permanent delete request
        $permanent = isset($_GET['permanent']) && $_GET['permanent'] === '1';

        if ($permanent) {
            // Permanent deletion - only for already archived users
            // Check if user is archived first
            if ($email !== '') {
                $checkStmt = $pdo->prepare('SELECT archived FROM users WHERE email = ?');
                $checkStmt->execute([$email]);
            } else {
                $checkStmt = $pdo->prepare('SELECT archived FROM users WHERE id = ?');
                $checkStmt->execute([$id]);
            }
            $archivedUser = $checkStmt->fetch();

            if (!$archivedUser || ($archivedUser['archived'] ?? 0) != 1) {
                json_response(['ok' => false, 'error' => 'Can only permanently delete archived users'], 400);
            }

            // Permanently delete from database
            // First remove dependent rows in other tables to avoid FK constraint errors
            $uid = (int)($user['id'] ?? $id);
            $dependentDeletes = [
                // table => sql (use user_id or submitted_by where appropriate)
                'password_reset_tokens' => 'DELETE FROM password_reset_tokens WHERE user_id = ?',
                'password_change_logs' => 'DELETE FROM password_change_logs WHERE user_id = ?',
                'submissions' => 'DELETE FROM submissions WHERE submitted_by = ?'
            ];

            foreach ($dependentDeletes as $tbl => $sql) {
                try {
                    $d = $pdo->prepare($sql);
                    $d->execute([$uid]);
                } catch (Throwable $e) {
                    // Continue; some tables may not exist
                }
            }

            try {
                if ($email !== '') {
                    $del = $pdo->prepare('DELETE FROM users WHERE email = ?');
                    $del->execute([$email]);
                } else {
                    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
                    $del->execute([$id]);
                }

                if ($del->rowCount() === 0) {
                    json_response(['ok' => false, 'error' => 'Delete failed or user already removed'], 500);
                }
            } catch (Throwable $eDel) {
                // If it's a FK constraint error, attempt a retry with foreign key checks disabled
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    if ($email !== '') {
                        $del = $pdo->prepare('DELETE FROM users WHERE email = ?');
                        $del->execute([$email]);
                    } else {
                        $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
                        $del->execute([$id]);
                    }
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    if ($del->rowCount() === 0) {
                        json_response(['ok' => false, 'error' => 'Delete retried but no rows affected'], 500);
                    }
                } catch (Throwable $e2) {
                    // Attempt to re-enable FK checks and rethrow
                    try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $_) {}
                    json_response(['ok' => false, 'error' => 'Delete failed due to database constraints', 'detail' => $e2->getMessage()], 500);
                }
            }
        } else {
            // Archive user instead of deleting
            if ($email !== '') {
                $upd = $pdo->prepare('UPDATE users SET archived = 1 WHERE email = ?');
                $upd->execute([$email]);
            } else {
                $upd = $pdo->prepare('UPDATE users SET archived = 1 WHERE id = ?');
                $upd->execute([$id]);
            }

            if (($upd->rowCount() ?? 0) === 0) {
                json_response(['ok' => false, 'error' => 'Archive failed or user not found'], 500);
            }
        }

        json_response(['ok' => true]);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'Database error during delete/archive', 'detail' => $e->getMessage()], 500);
    }
}

function handle_patch(): void {
    require_abc_role();
    $pdo = get_pdo();
    $body = read_json_body();
    
    $email = isset($body['email']) ? strtolower(trim((string)$body['email'])) : '';
    $action = isset($body['action']) ? trim((string)$body['action']) : '';
    
    if ($email === '' || $action === '') {
        json_response(['ok' => false, 'error' => 'Missing email or action'], 400);
    }
    
    // Find user
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        json_response(['ok' => false, 'error' => 'User not found'], 404);
    }
    
    if ($user['role'] === 'ABC') {
        json_response(['ok' => false, 'error' => 'Cannot modify ABC'], 403);
    }
    
    if ($action === 'restore') {
        // Restore archived user
        $update = $pdo->prepare('UPDATE users SET archived = 0 WHERE email = ?');
        $update->execute([$email]);
        json_response(['ok' => true, 'message' => 'User restored successfully']);
    } else {
        json_response(['ok' => false, 'error' => 'Invalid action'], 400);
    }
}

?>

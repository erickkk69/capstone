<?php
declare(strict_types=1);

session_start();

require_once 'config.php';

// Get PDO connection
$pdo = get_pdo();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get announcements
        $role = $_SESSION['role'] ?? '';
        $barangay = $_SESSION['barangay'] ?? '';
        $archived = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;
        
        if ($role === 'ABC') {
            // ABC can see all announcements they created
            $stmt = $pdo->prepare("
                SELECT a.*, u.email as creator_email, u.barangay as creator_barangay
                FROM announcements a
                LEFT JOIN users u ON a.created_by = u.id
                WHERE a.archived = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$archived]);
        } else {
            // Barangay Secretary sees announcements for their barangay
            $stmt = $pdo->prepare("
                SELECT a.*, u.email as creator_email, u.barangay as creator_barangay,
                       CASE WHEN ar.user_id IS NOT NULL THEN 1 ELSE 0 END as is_read
                FROM announcements a
                LEFT JOIN users u ON a.created_by = u.id
                LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = ?
                WHERE (a.target_barangay = ? OR a.target_barangay = 'All Barangays') 
                AND a.archived = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id'], $barangay, $archived]);
        }
        
        $announcements = $stmt->fetchAll();
        
        echo json_encode([
            'ok' => true,
            'announcements' => $announcements
        ]);
        
    } elseif ($method === 'POST') {
        // Create announcement (ABC only)
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ABC') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Only ABC can create announcements']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        $target_barangay = trim($input['target_barangay'] ?? '');
        
        if (empty($title) || empty($content) || empty($target_barangay)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Title, content, and target barangay are required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, content, target_barangay, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$title, $content, $target_barangay, $_SESSION['user_id']]);
        
        echo json_encode([
            'ok' => true,
            'message' => 'Announcement created successfully',
            'announcement_id' => $pdo->lastInsertId()
        ]);
        
    } elseif ($method === 'PUT') {
        // Mark announcement as read (Barangay Secretary)
        $input = json_decode(file_get_contents('php://input'), true);
        $announcement_id = (int)($input['announcement_id'] ?? 0);
        
        if ($announcement_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid announcement ID']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET is_read = 1 
            WHERE id = ?
        ");
        $stmt->execute([$announcement_id]);
        
        echo json_encode([
            'ok' => true,
            'message' => 'Announcement marked as read'
        ]);
        
    } elseif ($method === 'PATCH') {
        // Restore or permanently delete announcement (ABC only)
        if ($_SESSION['role'] !== 'ABC') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Only ABC can perform this action']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $announcement_id = (int)($input['announcement_id'] ?? 0);
        $action = $input['action'] ?? '';
        
        if ($announcement_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid announcement ID']);
            exit;
        }
        
        if ($action === 'restore') {
            // Restore archived announcement
            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET archived = 0 
                WHERE id = ? AND created_by = ? AND archived = 1
            ");
            $stmt->execute([$announcement_id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'ok' => true,
                    'message' => 'Announcement restored successfully'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Announcement not found or you do not have permission']);
            }
            
        } elseif ($action === 'permanent_delete') {
            // Permanently delete announcement
            $stmt = $pdo->prepare("
                DELETE FROM announcements 
                WHERE id = ? AND created_by = ? AND archived = 1
            ");
            $stmt->execute([$announcement_id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'ok' => true,
                    'message' => 'Announcement permanently deleted'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Announcement not found, not archived, or you do not have permission']);
            }
            
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid action']);
        }
        
    } elseif ($method === 'DELETE') {
        // Archive/delete announcement (ABC only)
        if ($_SESSION['role'] !== 'ABC') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Only ABC can delete announcements']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $announcement_id = (int)($input['announcement_id'] ?? 0);
        
        if ($announcement_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid announcement ID']);
            exit;
        }
        
        // Soft delete
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET archived = 1 
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([$announcement_id, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'ok' => true,
                'message' => 'Announcement deleted successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Announcement not found or you do not have permission']);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database error',
        'detail' => $e->getMessage()
    ]);
}

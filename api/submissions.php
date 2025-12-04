<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = current_user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = get_pdo();

if ($method === 'GET') {
    try {
        $id = $_GET['id'] ?? null;
        $barangayFilter = $_GET['barangay'] ?? null;
        $statusFilter = $_GET['status'] ?? null;
        $archivedFilter = $_GET['archived'] ?? '0';
        
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Submission not found']);
                exit;
            }
            
            $submission['created_at'] = $submission['uploaded_at'] ?? $submission['created_at'];
            $submission['barangay'] = $submission['uploaded_by'];
            $submission['filepath'] = 'uploads/' . $submission['filename'];
            
            if (empty($submission['period']) && !empty($submission['reporting_period'])) {
                $submission['period'] = $submission['reporting_period'];
            }
            
            echo json_encode(['ok' => true, 'submission' => $submission]);
            exit;
        }
        
        $activeBarangaysStmt = $pdo->query('SELECT DISTINCT barangay FROM users WHERE archived = 0 OR archived IS NULL');
        $activeBarangays = $activeBarangaysStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($activeBarangays)) {
            echo json_encode(['ok' => true, 'submissions' => []]);
            exit;
        }
        
        $sql = 'SELECT s.* FROM submissions s WHERE 1=1';
        $params = [];
        
        $placeholders = str_repeat('?,', count($activeBarangays) - 1) . '?';
        $sql .= " AND s.uploaded_by IN ($placeholders)";
        $params = array_merge($params, $activeBarangays);
        
        $sql .= ' AND (s.archived = ? OR s.archived IS NULL)';
        $params[] = $archivedFilter;
        
        if ($user['role'] === 'Barangay Secretary') {
            $sql .= ' AND s.uploaded_by = ?';
            $params[] = $user['barangay'];
        } elseif ($barangayFilter) {
            $sql .= ' AND s.uploaded_by = ?';
            $params[] = $barangayFilter;
        }
        
        if ($statusFilter) {
            $sql .= ' AND s.status = ?';
            $params[] = $statusFilter;
        }
        
        $sql .= ' ORDER BY s.uploaded_at DESC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $submissions = $stmt->fetchAll();
        
        foreach ($submissions as &$sub) {
            $sub['created_at'] = $sub['uploaded_at'];
            $sub['barangay'] = $sub['uploaded_by'];
            $sub['filepath'] = 'uploads/' . $sub['filename'];
            
            if (empty($sub['period']) && !empty($sub['reporting_period'])) {
                $sub['period'] = $sub['reporting_period'];
            }
        }
        
        echo json_encode(['ok' => true, 'submissions' => $submissions]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    try {
        if ($user['role'] !== 'Barangay Secretary') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Only Barangay Secretaries can submit reports']);
            exit;
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'File upload failed or no file provided']);
            exit;
        }
        
        $file = $_FILES['file'];
        $title = $_POST['title'] ?? '';
        $category = $_POST['category'] ?? '';
        $period = $_POST['period'] ?? '';
        
        if (empty($title) || empty($category) || empty($period)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            exit;
        }
        
        $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                         'application/msword', 'application/vnd.ms-excel', 
                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid file type. Only PDF, DOCX, DOC, XLS, XLSX allowed']);
            exit;
        }
        
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $uploadsDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
            exit;
        }
        
        $stmt = $pdo->prepare(
            'INSERT INTO submissions (title, category, period, reporting_period, filename, original_name, uploaded_by, status, uploaded_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $title,
            $category,
            $period,
            $period,
            $filename,
            $file['name'],
            $user['barangay'],
            'pending'
        ]);
        
        echo json_encode([
            'ok' => true, 
            'message' => 'Report submitted successfully',
            'submission_id' => $pdo->lastInsertId()
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'PUT' || $method === 'PATCH') {
    try {
        if ($user['role'] !== 'ABC') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Only ABC can update submission status']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $status = $input['status'] ?? null;
        $remarks = $input['remarks'] ?? '';
        $action = $input['action'] ?? null;
        $statusChangedAt = $input['status_changed_at'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing submission id']);
            exit;
        }
        
        if ($action === 'add_comment') {
            if (empty($remarks)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Comment cannot be empty']);
                exit;
            }
            
            $stmt = $pdo->prepare('UPDATE submissions SET remarks = ?, reviewed_at = NOW() WHERE id = ?');
            $stmt->execute([$remarks, $id]);
            
            echo json_encode(['ok' => true, 'message' => 'Comment added successfully']);
            exit;
        }
        
        if (!$status) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing status']);
            exit;
        }
        
        $validStatuses = ['pending', 'review', 'approved', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid status']);
            exit;
        }
        
        $stmt = $pdo->prepare('UPDATE submissions SET status = ?, remarks = ?, reviewed_at = NOW(), status_changed_at = ? WHERE id = ?');
        $stmt->execute([$status, $remarks, $statusChangedAt, $id]);
        
        echo json_encode(['ok' => true, 'message' => 'Submission status updated']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    try {
        $action = $_GET['action'] ?? 'delete';
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing submission id']);
            exit;
        }
        
        if ($action === 'archive') {
            $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Submission not found']);
                exit;
            }
            
            if ($user['role'] === 'Barangay Secretary') {
                if ($submission['uploaded_by'] !== $user['barangay']) {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Can only archive your own submissions']);
                    exit;
                }
                
                if ($submission['status'] !== 'pending') {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Cannot archive documents that have been processed by ABC']);
                    exit;
                }
            }
            
            $stmt = $pdo->prepare('UPDATE submissions SET archived = 1 WHERE id = ?');
            $stmt->execute([$id]);
            
            echo json_encode(['ok' => true, 'message' => 'Submission archived']);
            exit;
        }
        
        if ($action === 'restore') {
            $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Submission not found']);
                exit;
            }
            
            if ($user['role'] === 'Barangay Secretary') {
                if ($submission['uploaded_by'] !== $user['barangay']) {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Can only restore your own submissions']);
                    exit;
                }
                
                if ($submission['status'] !== 'pending') {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Cannot restore documents that have been processed by ABC']);
                    exit;
                }
            }
            
            $stmt = $pdo->prepare('UPDATE submissions SET archived = 0 WHERE id = ?');
            $stmt->execute([$id]);
            
            echo json_encode(['ok' => true, 'message' => 'Submission restored']);
            exit;
        }
        
        $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
        $stmt->execute([$id]);
        $submission = $stmt->fetch();
        
        if (!$submission) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Submission not found']);
            exit;
        }
        
        if ($user['role'] === 'Barangay Secretary') {
            if ($submission['uploaded_by'] !== $user['barangay']) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Can only delete your own submissions']);
                exit;
            }
            
            if ($submission['status'] !== 'pending') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Cannot delete documents that have been processed by ABC']);
                exit;
            }
        }
        
        $filepath = __DIR__ . '/../uploads/' . $submission['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        $stmt = $pdo->prepare('DELETE FROM submissions WHERE id = ?');
        $stmt->execute([$id]);
        
        echo json_encode(['ok' => true, 'message' => 'Submission deleted']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
?>

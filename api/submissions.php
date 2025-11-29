<?php
// api/submissions.php - Handle document submissions from barangays

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = current_user();

// Only authenticated users can access
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = get_pdo();

// GET - Retrieve submissions
if ($method === 'GET') {
    try {
        $id = $_GET['id'] ?? null;
        $barangayFilter = $_GET['barangay'] ?? null;
        $statusFilter = $_GET['status'] ?? null;
        $archivedFilter = $_GET['archived'] ?? '0'; // Default to non-archived
        
        // If ID is provided, return single submission
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Submission not found']);
                exit;
            }
            
            // Normalize column names for frontend compatibility
            $submission['created_at'] = $submission['uploaded_at'] ?? $submission['created_at'];
            $submission['barangay'] = $submission['uploaded_by']; // uploaded_by contains barangay name
            $submission['filepath'] = 'uploads/' . $submission['filename']; // Construct filepath
            
            // Use reporting_period if period is empty
            if (empty($submission['period']) && !empty($submission['reporting_period'])) {
                $submission['period'] = $submission['reporting_period'];
            }
            
            echo json_encode(['ok' => true, 'submission' => $submission]);
            exit;
        }
        
        // Get list of barangays that have at least one active (non-archived) user
        $activeBarangaysStmt = $pdo->query('SELECT DISTINCT barangay FROM users WHERE archived = 0 OR archived IS NULL');
        $activeBarangays = $activeBarangaysStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($activeBarangays)) {
            // No active barangays, return empty result
            echo json_encode(['ok' => true, 'submissions' => []]);
            exit;
        }
        
        // Build query to filter submissions
        $sql = 'SELECT s.* FROM submissions s WHERE 1=1';
        $params = [];
        
        // Only show submissions from barangays with active users
        $placeholders = str_repeat('?,', count($activeBarangays) - 1) . '?';
        $sql .= " AND s.uploaded_by IN ($placeholders)";
        $params = array_merge($params, $activeBarangays);
        
        // Filter by archived status of submissions
        $sql .= ' AND (s.archived = ? OR s.archived IS NULL)';
        $params[] = $archivedFilter;
        
        // Filter by barangay for barangay secretaries
        if ($user['role'] === 'Barangay Secretary') {
            $sql .= ' AND s.uploaded_by = ?';
            $params[] = $user['barangay'];
        } elseif ($barangayFilter) {
            // ABC can filter by specific barangay
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
        
        // Normalize column names for frontend compatibility
        foreach ($submissions as &$sub) {
            $sub['created_at'] = $sub['uploaded_at'];
            $sub['barangay'] = $sub['uploaded_by']; // uploaded_by contains barangay name
            $sub['filepath'] = 'uploads/' . $sub['filename']; // Construct filepath
            
            // Use reporting_period if period is empty
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

// POST - Create new submission
if ($method === 'POST') {
    try {
        // Only Barangay Secretaries can submit
        if ($user['role'] !== 'Barangay Secretary') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Only Barangay Secretaries can submit reports']);
            exit;
        }
        
        // Handle file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'File upload failed or no file provided']);
            exit;
        }
        
        $file = $_FILES['file'];
        $title = $_POST['title'] ?? '';
        $category = $_POST['category'] ?? '';
        $period = $_POST['period'] ?? '';
        
        // Validate inputs
        if (empty($title) || empty($category) || empty($period)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            exit;
        }
        
        // Validate file type
        $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                         'application/msword', 'application/vnd.ms-excel', 
                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid file type. Only PDF, DOCX, DOC, XLS, XLSX allowed']);
            exit;
        }
        
        // Create uploads directory if not exists
        $uploadsDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $uploadsDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
            exit;
        }
        
        // Insert into database
        // Note: uploaded_by stores barangay name, not user ID
        $stmt = $pdo->prepare(
            'INSERT INTO submissions (title, category, period, reporting_period, filename, original_name, uploaded_by, status, uploaded_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $title,
            $category,
            $period,
            $period, // Also store in reporting_period for compatibility
            $filename,
            $file['name'],
            $user['barangay'], // Store barangay name
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

// PUT/PATCH - Update submission status (ABC only)
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
        
        // Handle comment addition
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
        
        // Handle status update
        if (!$status) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing status']);
            exit;
        }
        
        // Validate status
        $validStatuses = ['pending', 'review', 'approved', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid status']);
            exit;
        }
        
        // Update with status change timestamp
        $stmt = $pdo->prepare('UPDATE submissions SET status = ?, remarks = ?, reviewed_at = NOW(), status_changed_at = ? WHERE id = ?');
        $stmt->execute([$status, $remarks, $statusChangedAt, $id]);
        
        echo json_encode(['ok' => true, 'message' => 'Submission status updated']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// DELETE - Delete submission
if ($method === 'DELETE') {
    try {
        // Check if this is an archive or restore action
        $action = $_GET['action'] ?? 'delete';
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing submission id']);
            exit;
        }
        
        // Handle archive action
        if ($action === 'archive') {
            // Check ownership/permission
            $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Submission not found']);
                exit;
            }
            
            // Check permissions - Barangay Secretary can only archive their own
            if ($user['role'] === 'Barangay Secretary') {
                if ($submission['uploaded_by'] !== $user['barangay']) {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Can only archive your own submissions']);
                    exit;
                }
                
                // Prevent archiving if ABC has already processed (status changed from pending)
                if ($submission['status'] !== 'pending') {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Cannot archive documents that have been processed by ABC']);
                    exit;
                }
            }
            
            // Archive the submission
            $stmt = $pdo->prepare('UPDATE submissions SET archived = 1 WHERE id = ?');
            $stmt->execute([$id]);
            
            echo json_encode(['ok' => true, 'message' => 'Submission archived']);
            exit;
        }
        
        // Handle restore action
        if ($action === 'restore') {
            // Check ownership/permission
            $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
            $stmt->execute([$id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Submission not found']);
                exit;
            }
            
            // Check permissions - Barangay Secretary can only restore their own
            if ($user['role'] === 'Barangay Secretary') {
                if ($submission['uploaded_by'] !== $user['barangay']) {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Can only restore your own submissions']);
                    exit;
                }
                
                // Prevent restoring if ABC has already processed (status changed from pending)
                if ($submission['status'] !== 'pending') {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Cannot restore documents that have been processed by ABC']);
                    exit;
                }
            }
            
            // Restore the submission
            $stmt = $pdo->prepare('UPDATE submissions SET archived = 0 WHERE id = ?');
            $stmt->execute([$id]);
            
            echo json_encode(['ok' => true, 'message' => 'Submission restored']);
            exit;
        }
        
        // Handle actual delete
        // ABC can delete any, Barangay can only delete their own pending submissions
        
        // Check ownership/permission
        $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
        $stmt->execute([$id]);
        $submission = $stmt->fetch();
        
        if (!$submission) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Submission not found']);
            exit;
        }
        
        // Check permissions
        if ($user['role'] === 'Barangay Secretary') {
            // Barangay Secretary can delete from archived section
            if ($submission['uploaded_by'] !== $user['barangay']) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Can only delete your own submissions']);
                exit;
            }
            
            // Prevent deleting if ABC has already processed (status changed from pending)
            if ($submission['status'] !== 'pending') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Cannot delete documents that have been processed by ABC']);
                exit;
            }
        }
        
        // Delete file
        $filepath = __DIR__ . '/../uploads/' . $submission['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        // Delete from database
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

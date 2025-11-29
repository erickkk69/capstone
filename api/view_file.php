<?php
// api/view_file.php - Serve uploaded files securely

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Only authenticated users can view files
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get filename from query parameter
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    die('No file specified');
}

// Security: Prevent directory traversal
$filename = basename($filename);

// Build full file path
$filepath = __DIR__ . '/../uploads/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found: ' . htmlspecialchars($filename));
}

// Check if it's a valid file (not a directory)
if (!is_file($filepath)) {
    http_response_code(403);
    die('Invalid file');
}

// Get file extension
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Set appropriate content type
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Get the original filename from database if available
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT original_name FROM submissions WHERE filename = ?');
$stmt->execute([$filename]);
$submission = $stmt->fetch();

$originalName = $submission['original_name'] ?? $filename;

// Check if download is requested (for download button) or inline view (for preview)
$isDownload = isset($_GET['download']);

// Set headers
if ($isDownload) {
    // Force download - use application/octet-stream to prevent browser from opening
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Transfer-Encoding: binary');
} else {
    // Try to display inline (for preview)
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: inline; filename="' . $originalName . '"');
}
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Output file
readfile($filepath);
exit;

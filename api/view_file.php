<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Add CORS headers for better browser compatibility
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    die('No file specified');
}

// Clean filename - remove any directory traversal attempts
$filename = basename($filename);
$filename = str_replace(['..', '/', '\\'], '', $filename);

$filepath = __DIR__ . '/../uploads/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found: ' . htmlspecialchars($filename));
}

if (!is_file($filepath)) {
    http_response_code(403);
    die('Invalid file');
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'bmp' => 'image/bmp',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'log' => 'text/plain',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'audio/ogg',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'm4a' => 'audio/mp4'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT original_name FROM submissions WHERE filename = ?');
$stmt->execute([$filename]);
$submission = $stmt->fetch();

$originalName = $submission['original_name'] ?? $filename;

$isDownload = isset($_GET['download']);

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

if ($isDownload) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($originalName) . '"');
    header('Content-Transfer-Encoding: binary');
} else {
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: inline; filename="' . addslashes($originalName) . '"');
}
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');

// Use fpassthru for better memory handling with large files
$handle = fopen($filepath, 'rb');
if ($handle) {
    fpassthru($handle);
    fclose($handle);
} else {
    readfile($filepath);
}
exit;

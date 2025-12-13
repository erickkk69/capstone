<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = current_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    die('No file specified');
}

$filename = basename($filename);
$filename = str_replace(['..', '/', '\\'], '', $filename);

$filepath = __DIR__ . '/../uploads/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    die('File not found: ' . htmlspecialchars($filename));
}

if (!is_file($filepath)) {
    http_response_code(403);
    header('Content-Type: text/plain');
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

// Clear any previous output buffers to prevent corruption
while (ob_get_level()) {
    ob_end_clean();
}

// Set proper headers for file download/view
if ($isDownload) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $originalName) . '"');
} else {
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $originalName) . '"');
}

header('Content-Length: ' . filesize($filepath));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// Output the file
$handle = fopen($filepath, 'rb');
if ($handle !== false) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
} else {
    readfile($filepath);
}
exit;

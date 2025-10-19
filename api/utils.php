<?php
// api/utils.php - Common helpers

declare(strict_types=1);

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// Simple CORS for same-origin; adjust if you host frontend separately
function allow_cors(): void {
    // If frontend is served from same XAMPP host, you might not need this.
    header('X-Content-Type-Options: nosniff');
}

?>

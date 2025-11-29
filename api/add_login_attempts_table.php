<?php
// api/add_login_attempts_table.php - Add login_attempts table

declare(strict_types=1);

// Database connection parameters
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'capstone_db';
const DB_USER = 'root';
const DB_PASS = '';

header('Content-Type: application/json');

try {
    // Connect to database
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'login_attempts'");
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'ok' => true,
            'message' => 'login_attempts table already exists.',
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Create login_attempts table
    $pdo->exec("
        CREATE TABLE login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            lockout_count INT NOT NULL DEFAULT 0,
            locked_until TIMESTAMP NULL DEFAULT NULL,
            last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_locked_until (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo json_encode([
        'ok' => true,
        'message' => 'login_attempts table created successfully!',
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to create login_attempts table',
        'detail' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

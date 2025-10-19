<?php
// api/setup.php - One-time database setup and seeding

declare(strict_types=1);

header('Content-Type: application/json');

// This script attempts to create the database (if not exists) and tables.
// It uses the same credentials as config.php but needs initial connection to mysql without db.

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'capstone_db';
const DB_USER = 'root';
const DB_PASS = '';

try {
    // Connect to server (no db) to ensure DB exists
    $dsnServer = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
    $pdoServer = new PDO($dsnServer, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdoServer->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    // Connect to db
    $dsnDb = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsnDb, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create users table
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(64) NOT NULL,
            barangay VARCHAR(128) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Seed ABC Secretary if not exists
    $email = 'abcsecretary@mabini.gov';
    $password = 'abcpassword';
    $role = 'ABC Secretary';
    $barangay = 'All';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (email, password_hash, role, barangay) VALUES (?, ?, ?, ?)');
        $ins->execute([$email, $hash, $role, $barangay]);
    }

    echo json_encode(['ok' => true, 'message' => 'Setup complete. Default ABC user created.', 'default_login' => ['email' => $email, 'password' => $password]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

?>

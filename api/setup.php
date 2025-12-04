<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'capstone_db';
const DB_USER = 'root';
const DB_PASS = '';

header('Content-Type: application/json');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS login_attempts");
    $pdo->exec("DROP TABLE IF EXISTS password_change_logs");
    $pdo->exec("DROP TABLE IF EXISTS password_reset_requests");
    $pdo->exec("DROP TABLE IF EXISTS submissions");
    $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('ABC', 'Barangay Secretary') NOT NULL DEFAULT 'Barangay Secretary',
        barangay VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity DATETIME NULL DEFAULT NULL,
        archived TINYINT(1) DEFAULT 0,
        account_locked TINYINT(1) DEFAULT 0,
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_barangay (barangay),
        INDEX idx_archived (archived)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            uploaded_by VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'review', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            remarks TEXT DEFAULT NULL,
            period VARCHAR(100) DEFAULT NULL,
            reporting_period VARCHAR(100) DEFAULT NULL,
            document_type VARCHAR(100) DEFAULT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            status_changed_at VARCHAR(255) DEFAULT NULL,
            archived TINYINT(1) DEFAULT 0,
            user_id INT,
            INDEX idx_status (status),
            INDEX idx_uploaded_by (uploaded_by),
            INDEX idx_archived (archived),
            INDEX idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE password_reset_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            user_role VARCHAR(50) NOT NULL,
            user_barangay VARCHAR(255) NOT NULL,
            new_password_hash VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            reviewed_at TIMESTAMP NULL,
            reviewed_by INT NULL,
            rejection_reason TEXT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE password_change_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            user_role VARCHAR(50) NOT NULL,
            user_barangay VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            changed_by ENUM('user', 'admin') NOT NULL DEFAULT 'user',
            INDEX idx_user_id (user_id),
            INDEX idx_changed_at (changed_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            lockout_count INT NOT NULL DEFAULT 0,
            locked_until TIMESTAMP NULL DEFAULT NULL,
            last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_locked_until (locked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaultEmail = 'abcportal@gmail.com';
    $defaultPassword = 'abcpassword';
    $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, barangay) VALUES (?, ?, 'ABC', 'Municipal Office')");
    $stmt->execute([$defaultEmail, $passwordHash]);

    echo json_encode([
        'ok' => true,
        'message' => 'Setup complete! Database reset successfully.',
        'details' => [
            'database' => DB_NAME,
            'tables_created' => ['users', 'submissions', 'password_reset_requests', 'password_change_logs', 'login_attempts'],
            'default_account' => [
                'email' => $defaultEmail,
                'password' => $defaultPassword,
                'role' => 'ABC'
            ]
        ]
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database setup failed',
        'detail' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

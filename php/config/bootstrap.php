<?php
declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Development helpers: enable verbose errors when running locally
if (!defined('DEV_MODE')) {
    define('DEV_MODE', true);
}
if (defined('DEV_MODE') && DEV_MODE) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/database.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)) {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($originHost === ($_SERVER['SERVER_NAME'] ?? $originHost) || in_array($originHost, ['localhost', '127.0.0.1'], true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Vary: Origin');
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function isStrongPassword(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

function ensureDatabaseSchema(): void
{
    global $pdo;

    if (!$pdo) {
        return;
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role ENUM('donor','hospital','admin') NOT NULL DEFAULT 'donor',
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            google_sub VARCHAR(255) DEFAULT NULL UNIQUE,
            first_name VARCHAR(120) DEFAULT NULL,
            last_name VARCHAR(120) DEFAULT NULL,
            hospital_name VARCHAR(180) DEFAULT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            blood_group VARCHAR(10) DEFAULT NULL,
            profile_image VARCHAR(255) DEFAULT NULL,
            totp_secret VARCHAR(64) DEFAULT NULL,
            totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
            reset_token VARCHAR(255) DEFAULT NULL,
            reset_expires_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS hospitals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            hospital_name VARCHAR(180) NOT NULL,
            address VARCHAR(255) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            state VARCHAR(120) DEFAULT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            verification_status ENUM('pending','verified') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS blood_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hospital_id INT NOT NULL,
            blood_group VARCHAR(10) NOT NULL,
            units INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_hospital_blood (hospital_id, blood_group),
            FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(180) DEFAULT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            donor_name VARCHAR(180) NOT NULL,
            blood_group VARCHAR(10) NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            hospital_name VARCHAR(180) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS blood_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            patient_name VARCHAR(180) NOT NULL,
            hospital_name VARCHAR(180) NOT NULL,
            blood_group VARCHAR(10) NOT NULL,
            units INT NOT NULL DEFAULT 1,
            priority VARCHAR(30) NOT NULL DEFAULT 'Normal',
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            request_date DATE NOT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'totp_secret'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL, ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0");
    }

    $googleColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_sub'")->fetch();
    if (!$googleColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) DEFAULT NULL UNIQUE");
    }

    $resetTokenColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'")->fetch();
    if (!$resetTokenColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL, ADD COLUMN reset_expires_at DATETIME DEFAULT NULL");
    }

    $profileColumns = [
        'date_of_birth' => 'DATE DEFAULT NULL',
        'gender' => 'VARCHAR(30) DEFAULT NULL',
        'state' => 'VARCHAR(120) DEFAULT NULL',
        'city' => 'VARCHAR(120) DEFAULT NULL',
        'address' => 'VARCHAR(255) DEFAULT NULL',
        'emergency_contact_name' => 'VARCHAR(180) DEFAULT NULL',
        'emergency_phone' => 'VARCHAR(40) DEFAULT NULL',
        'registration_number' => 'VARCHAR(120) DEFAULT NULL',
        'operating_hours' => 'VARCHAR(120) DEFAULT NULL',
    ];
    foreach ($profileColumns as $column => $definition) {
        $exists = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($column))->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
        }
    }
}

ensureDatabaseSchema();

function requireLogin(): int
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated.'
        ]);
        exit;
    }

    return (int) $_SESSION['user_id'];
}

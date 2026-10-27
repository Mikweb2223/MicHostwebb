<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();

    // Store per-user settings in a small key/value table.
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        `key` VARCHAR(120) NOT NULL,
        `value` TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_user_key (user_id, `key`),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('SELECT `key`, `value` FROM user_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $settings = [
            'notifications' => 'disabled',
            'emailNotifications' => 'disabled'
        ];

        foreach ($stmt->fetchAll() as $setting) {
            if (array_key_exists($setting['key'], $settings)) {
                $settings[$setting['key']] = $setting['value'];
            }
        }

        echo json_encode(['success' => true, 'settings' => $settings]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = [];
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
    } else {
        $input = $_POST;
    }

    $allowed = ['notifications', 'emailNotifications'];
    $updated = [];

    foreach ($allowed as $k) {
        if (array_key_exists($k, $input)) {
            $v = (string)$input[$k];
            $stmt = $pdo->prepare('INSERT INTO user_settings (user_id, `key`, `value`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
            $stmt->execute([$userId, $k, $v]);
            $updated[$k] = $v;
        }
    }

    echo json_encode(['success' => true, 'updated' => $updated]);
} catch (Throwable $e) {
    error_log('Update settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save settings.']);
}

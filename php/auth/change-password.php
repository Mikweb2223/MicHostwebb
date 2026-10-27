<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Accept JSON or form data
    $input = [];
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
    } else {
        $input = $_POST;
    }

    $current = trim($input['current_password'] ?? '');
    $new = trim($input['new_password'] ?? '');

    if ($current === '' || $new === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing passwords']);
        exit;
    }

    if (strlen($new) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password too short']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if (!password_verify($current, $row['password'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $up = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $up->execute([$hash, $userId]);

    echo json_encode(['success' => true, 'message' => 'Password updated']);
} catch (Throwable $e) {
    error_log('Change password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to change password']);
}

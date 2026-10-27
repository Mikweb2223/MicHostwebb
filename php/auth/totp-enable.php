<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/totp.php';

try {
    $userId = requireLogin();
    $code = (string)($_POST['code'] ?? $_REQUEST['code'] ?? '');
    $stmt = $pdo->prepare('SELECT totp_secret FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $secret = (string)$stmt->fetchColumn();

    if ($secret === '' || !verifyTotpCode($secret, $code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The authenticator code is invalid or expired.']);
        exit;
    }

    $update = $pdo->prepare('UPDATE users SET totp_enabled = 1 WHERE id = ?');
    $update->execute([$userId]);
    echo json_encode(['success' => true, 'message' => 'Google Authenticator is now enabled.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to enable authenticator.']);
}

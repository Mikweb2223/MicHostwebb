<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/totp.php';

try {
    $userId = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
    $code = (string)($_POST['code'] ?? $_REQUEST['code'] ?? '');
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'The login session has expired.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, role, first_name, last_name, hospital_name, totp_secret FROM users WHERE id = ? AND totp_enabled = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !verifyTotpCode((string)$user['totp_secret'], $code)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid authenticator code.']);
        exit;
    }

    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_role']);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_name'] = $user['hospital_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    echo json_encode(['success' => true, 'message' => 'Login successful.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to verify authenticator code.']);
}

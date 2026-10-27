<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/totp.php';

try {
    $userId = requireLogin();
    $stmt = $pdo->prepare('SELECT email, totp_secret, totp_enabled FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $secret = $user['totp_secret'] ?: generateTotpSecret();
    if (!$user['totp_secret']) {
        $update = $pdo->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 0 WHERE id = ?');
        $update->execute([$secret, $userId]);
    }

    $label = rawurlencode('MicHost:' . $user['email']);
    $issuer = rawurlencode('MicHost');
    $uri = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    echo json_encode(['success' => true, 'enabled' => (bool)$user['totp_enabled'], 'secret' => $secret, 'otpauth_uri' => $uri]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare authenticator setup.']);
}

<?php
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$credential = trim((string)($_POST['credential'] ?? ''));
$clientId = trim((string)(getenv('GOOGLE_CLIENT_ID') ?: ''));
if ($credential === '' || $clientId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Google sign-in is not configured.']);
    exit;
}

try {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential);
    $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $raw = file_get_contents($url, false, $context);
    $claims = is_string($raw) ? json_decode($raw, true) : null;
    $issuer = is_array($claims) ? (string)($claims['iss'] ?? '') : '';
    $expiresAt = is_array($claims) ? filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT) : false;
    if (!is_array($claims)
        || ($claims['aud'] ?? '') !== $clientId
        || !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
        || !$expiresAt
        || $expiresAt <= time()
        || empty($claims['sub'])
        || empty($claims['email'])
        || ($claims['email_verified'] ?? 'false') !== 'true') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid Google account.']);
        exit;
    }

    $googleSub = (string)$claims['sub'];
    $email = strtolower(trim((string)$claims['email']));
    $stmt = $pdo->prepare('SELECT id, google_sub FROM users WHERE google_sub = ? LIMIT 1');
    $stmt->execute([$googleSub]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare('SELECT id, google_sub FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    if ($user) {
        if (!empty($user['google_sub']) && !hash_equals((string)$user['google_sub'], $googleSub)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email is linked to a different Google account.']);
            exit;
        }
        if (empty($user['google_sub'])) {
            $update = $pdo->prepare('UPDATE users SET google_sub = ? WHERE id = ?');
            $update->execute([$googleSub, (int)$user['id']]);
        }
        $userId = (int)$user['id'];
    } else {
        $name = trim((string)($claims['name'] ?? 'Google User'));
        $parts = preg_split('/\s+/', $name, 2);
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (role, email, password_hash, google_sub, first_name, last_name, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute(['donor', $email, $passwordHash, $googleSub, $parts[0] ?? 'Google', $parts[1] ?? '', $claims['picture'] ?? null]);
        $userId = (int)$pdo->lastInsertId();
    }

    $profile = $pdo->prepare('SELECT id, role, email, first_name, last_name, hospital_name, phone, blood_group, profile_image, totp_enabled FROM users WHERE id = ?');
    $profile->execute([$userId]);
    $user = $profile->fetch();
    session_regenerate_id(true);
    if (!empty($user['totp_enabled'])) {
        $_SESSION['pending_2fa_user_id'] = $userId;
        $_SESSION['pending_2fa_role'] = $user['role'];
        $user['id'] = $userId;
        $user['name'] = $user['hospital_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        echo json_encode(['success' => true, 'requires_2fa' => true, 'message' => 'Enter the code from your authenticator app.', 'user' => $user]);
        exit;
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_name'] = $user['hospital_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $user['id'] = $userId;
    $user['name'] = $_SESSION['user_name'];
    echo json_encode(['success' => true, 'message' => 'Google login successful.', 'user' => $user]);
} catch (Throwable $e) {
    error_log('Google login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to log in with Google.']);
}
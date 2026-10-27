<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$email = trim((string)($_POST['email'] ?? $_REQUEST['email'] ?? ''));
$password = (string)($_POST['password'] ?? $_REQUEST['password'] ?? '');
$role = strtolower(trim((string)($_POST['role'] ?? $_REQUEST['role'] ?? '')));

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (!isStrongPassword($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, role, email, password_hash, first_name, last_name, hospital_name, phone, blood_group, profile_image, totp_enabled FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    if ($role !== '' && $user['role'] !== $role) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This account is not authorized for that login flow.']);
        exit;
    }

    if (!empty($user['totp_enabled'])) {
        $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
        $_SESSION['pending_2fa_role'] = $user['role'];
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'requires_2fa' => true,
            'message' => 'Enter the code from your authenticator app.'
        ]);
        exit;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_name'] = $user['hospital_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int)$user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'hospital_name' => $user['hospital_name'],
            'phone' => $user['phone'],
            'blood_group' => $user['blood_group'],
            'profile_image' => $user['profile_image'],
            'name' => $_SESSION['user_name']
        ]
    ]);
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to log in.']);
}

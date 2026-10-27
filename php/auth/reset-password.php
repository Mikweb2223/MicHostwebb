<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$token = trim((string)($_POST['token'] ?? $_REQUEST['token'] ?? ''));
$password = (string)($_POST['password'] ?? $_REQUEST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? $_REQUEST['confirm_password'] ?? '');

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reset token is missing.']);
    exit;
}

if ($password === '' || $confirm === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password and confirmation are required.']);
    exit;
}

if ($password !== $confirm) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires_at > NOW() LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$update = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?');
$update->execute([$hash, $user['id']]);

echo json_encode([
    'success' => true,
    'message' => 'Your password has been reset successfully. Please log in.'
]);

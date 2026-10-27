<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();
    $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = ?');
    $stmt->execute([$userId]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Notifications clear error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to clear notifications.']);
}

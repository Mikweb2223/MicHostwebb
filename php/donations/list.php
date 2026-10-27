<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();
    // For now return a placeholder list of donations tied to user
    $stmt = $pdo->prepare('SELECT id, donor_name, blood_group, units, donated_at FROM donations WHERE user_id = ? ORDER BY donated_at DESC');
    $stmt->execute([$userId]);
    $list = $stmt->fetchAll();
    echo json_encode(['success' => true, 'donations' => $list]);
} catch (Throwable $e) {
    error_log('Donations list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load donations.']);
}

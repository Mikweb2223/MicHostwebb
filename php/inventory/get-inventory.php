<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $userId = requireLogin();
    $stmt = $pdo->prepare('SELECT bi.id, bi.blood_group, bi.units, bi.created_at FROM blood_inventory bi INNER JOIN hospitals h ON h.id = bi.hospital_id WHERE h.user_id = ? ORDER BY bi.blood_group');
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'inventory' => $stmt->fetchAll()
    ]);
} catch (Throwable $e) {
    error_log('Get inventory error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load inventory.']);
}

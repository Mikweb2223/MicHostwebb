<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();

    // Example aggregates -- adapt to your schema and needs
    $donorsStmt = $pdo->query('SELECT COUNT(*) AS total FROM users WHERE role = "donor"');
    $donors = $donorsStmt->fetch();

    $inventoryStmt = $pdo->query('SELECT blood_group, SUM(units) AS units FROM blood_inventory GROUP BY blood_group');
    $inventory = $inventoryStmt->fetchAll();

    $requestsStmt = $pdo->query('SELECT status, COUNT(*) AS count FROM blood_requests GROUP BY status');
    $requests = $requestsStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'donors' => (int)($donors['total'] ?? 0),
        'inventory' => $inventory,
        'requests' => $requests
    ]);
} catch (Throwable $e) {
    error_log('Reports data error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load reports.']);
}

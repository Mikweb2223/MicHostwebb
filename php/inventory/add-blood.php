<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireLogin();

$bloodGroup = trim((string)($_POST['blood_group'] ?? $_REQUEST['blood_group'] ?? ''));
$units = filter_var($_POST['units'] ?? $_REQUEST['units'] ?? null, FILTER_VALIDATE_INT);

$allowed = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

if ($bloodGroup === '' || !in_array($bloodGroup, $allowed, true) || $units === false || $units < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid blood type and positive unit count are required.']);
    exit;
}

try {
    $hospitalStmt = $pdo->prepare('SELECT id FROM hospitals WHERE user_id = ? AND verification_status = "verified" LIMIT 1');
    $hospitalStmt->execute([$userId]);
    $hospital = $hospitalStmt->fetch();

    if (!$hospital) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hospital verification required.']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO blood_inventory (hospital_id, blood_group, units) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE units = units + VALUES(units)');
    $stmt->execute([(int)$hospital['id'], $bloodGroup, $units]);

    echo json_encode(['success' => true, 'message' => 'Inventory updated successfully.']);
} catch (Throwable $e) {
    error_log('Add blood error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update inventory.']);
}

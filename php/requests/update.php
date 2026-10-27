<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $userId = requireLogin();
    $id = filter_var($_POST['id'] ?? $_REQUEST['id'] ?? null, FILTER_VALIDATE_INT);
    $status = trim((string)($_POST['status'] ?? $_REQUEST['status'] ?? ''));
    if (!$id || !in_array($status, ['Approved', 'Rejected', 'Pending'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid request and status are required.']);
        exit;
    }

    if (($_SESSION['role'] ?? 'donor') === 'hospital') {
        $stmt = $pdo->prepare('UPDATE blood_requests br JOIN users u ON u.id = ? SET br.status = ? WHERE br.id = ? AND LOWER(TRIM(br.hospital_name)) = LOWER(TRIM(u.hospital_name)) AND u.role = "hospital"');
        $stmt->execute([$userId, $status, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE blood_requests SET status = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$status, $id, $userId]);
    }
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update blood request.']);
}

<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $userId = requireLogin();
    if (($_SESSION['role'] ?? 'donor') === 'hospital') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hospitals cannot delete donor requests.']);
        exit;
    }
    $id = filter_var($_POST['id'] ?? $_REQUEST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid request is required.']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM blood_requests WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete blood request.']);
}

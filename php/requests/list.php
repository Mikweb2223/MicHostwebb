<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();
    $viewerRole = $_SESSION['role'] ?? 'donor';

    if ($viewerRole === 'hospital') {
        $hospitalStmt = $pdo->prepare('SELECT hospital_name FROM users WHERE id = ? AND role = "hospital" LIMIT 1');
        $hospitalStmt->execute([$userId]);
        $hospitalName = (string)($hospitalStmt->fetchColumn() ?: '');

        $stmt = $pdo->prepare('SELECT br.id, br.patient_name, br.blood_group, br.units, br.hospital_name, br.priority, br.status, br.request_date, br.notes, CONCAT_WS(" ", u.first_name, u.last_name) AS donor_name, u.email AS donor_email FROM blood_requests br JOIN users u ON u.id = br.user_id WHERE LOWER(TRIM(br.hospital_name)) = LOWER(TRIM(?)) ORDER BY br.request_date DESC');
        $stmt->execute([$hospitalName]);
    } else {
        $stmt = $pdo->prepare('SELECT br.id, br.patient_name, br.blood_group, br.units, br.hospital_name, br.priority, br.status, br.request_date, br.notes, CONCAT_WS(" ", u.first_name, u.last_name) AS donor_name, u.email AS donor_email FROM blood_requests br JOIN users u ON u.id = br.user_id WHERE br.user_id = ? ORDER BY br.request_date DESC');
        $stmt->execute([$userId]);
    }

    echo json_encode([
        'success' => true,
        'viewer_role' => $viewerRole,
        'requests' => $stmt->fetchAll()
    ]);
} catch (Throwable $e) {
    error_log('List requests error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load blood requests.']);
}

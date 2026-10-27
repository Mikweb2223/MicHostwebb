<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();
    if (($_SESSION['role'] ?? 'donor') === 'hospital') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hospitals review donor requests instead of creating them.']);
        exit;
    }

    $patient = trim((string)($_POST['patient'] ?? $_REQUEST['patient'] ?? ''));
    $hospital = trim((string)($_POST['hospital'] ?? $_REQUEST['hospital'] ?? ''));
    $blood = trim((string)($_POST['blood'] ?? $_REQUEST['blood'] ?? ''));
    $units = filter_var($_POST['units'] ?? $_REQUEST['units'] ?? null, FILTER_VALIDATE_INT);
    $priority = trim((string)($_POST['priority'] ?? $_REQUEST['priority'] ?? 'Normal'));
    $date = trim((string)($_POST['date'] ?? $_REQUEST['date'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? $_REQUEST['notes'] ?? ''));

    if ($patient === '' || $hospital === '' || $blood === '' || $date === '' || $units === false || $units < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All request fields are required.']);
        exit;
    }

    $donorNameStmt = $pdo->prepare('SELECT CONCAT_WS(" ", first_name, last_name) AS donor_name FROM users WHERE id = ? LIMIT 1');
    $donorNameStmt->execute([$userId]);
    $donorNameRow = $donorNameStmt->fetch();
    $donorName = trim((string)($donorNameRow['donor_name'] ?? ''));

    $stmt = $pdo->prepare('INSERT INTO blood_requests (user_id, patient_name, hospital_name, blood_group, units, priority, status, request_date, notes) VALUES (?, ?, ?, ?, ?, ?, "Pending", ?, ?)');
    $stmt->execute([$userId, $patient, $hospital, $blood, $units, $priority, $date, $notes]);

    $hospitalUserStmt = $pdo->prepare('SELECT id FROM users WHERE role = "hospital" AND LOWER(TRIM(hospital_name)) = LOWER(TRIM(?)) LIMIT 1');
    $hospitalUserStmt->execute([$hospital]);
    $hospitalUser = $hospitalUserStmt->fetch();

    if ($hospitalUser) {
        $hospitalId = (int) $hospitalUser['id'];
        $notificationMessage = 'New blood request from ' . ($donorName !== '' ? $donorName : 'donor') . ': ' . $patient . ' needs ' . $blood . ' blood at ' . $hospital . ' on ' . $date . '.';

        $notifyStmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)');
        $notifyStmt->execute([$hospitalId, 'New Blood Request', $notificationMessage]);
    }

    echo json_encode(['success' => true, 'message' => 'Blood request submitted successfully.']);
} catch (Throwable $e) {
    error_log('Create request error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to submit blood request.']);
}

<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();
    $donor = trim((string)($_POST['donor'] ?? $_REQUEST['donor'] ?? ''));
    $blood = trim((string)($_POST['blood'] ?? $_REQUEST['blood'] ?? ''));
    $date = trim((string)($_POST['date'] ?? $_REQUEST['date'] ?? ''));
    $time = trim((string)($_POST['time'] ?? $_REQUEST['time'] ?? ''));
    $hospital = trim((string)($_POST['hospital'] ?? $_REQUEST['hospital'] ?? ''));

    if ($donor === '' || $blood === '' || $date === '' || $time === '' || $hospital === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All appointment fields are required.']);
        exit;
    }

    $donorNameStmt = $pdo->prepare('SELECT CONCAT_WS(" ", first_name, last_name) AS donor_name FROM users WHERE id = ? LIMIT 1');
    $donorNameStmt->execute([$userId]);
    $donorNameRow = $donorNameStmt->fetch();
    $donorName = trim((string)($donorNameRow['donor_name'] ?? ''));
    if ($donorName === '') {
        $donorName = $donor;
    }

    $appointmentStmt = $pdo->prepare(
        'INSERT INTO appointments (user_id, donor_name, blood_group, appointment_date, appointment_time, hospital_name, status) VALUES (?, ?, ?, ?, ?, ?, "Pending")'
    );
    $appointmentStmt->execute([$userId, $donor, $blood, $date, $time, $hospital]);

    $hospitalStmt = $pdo->prepare(
        'SELECT id FROM users WHERE role = "hospital" AND LOWER(TRIM(hospital_name)) = LOWER(TRIM(?)) LIMIT 1'
    );
    $hospitalStmt->execute([$hospital]);
    $hospitalUser = $hospitalStmt->fetch();

    if ($hospitalUser) {
        $hospitalId = (int) $hospitalUser['id'];
        $notificationTitle = 'New Appointment Request';
        $notificationMessage = $donorName . ' requested a ' . $blood . ' blood appointment for ' . $date . ' at ' . $time . ' at ' . $hospital . '.';

        $notificationStmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)'
        );
        $notificationStmt->execute([$hospitalId, $notificationTitle, $notificationMessage]);
    }

    echo json_encode(['success' => true, 'message' => 'Appointment created successfully.']);
} catch (Throwable $e) {
    error_log('Create appointment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to create appointment.']);
}

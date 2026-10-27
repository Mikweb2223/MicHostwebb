<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();
    $stmt = $pdo->prepare('SELECT id, donor_name, blood_group, appointment_date, appointment_time, hospital_name, status FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC');
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'appointments' => $stmt->fetchAll()
    ]);
} catch (Throwable $e) {
    error_log('List appointments error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load appointments.']);
}

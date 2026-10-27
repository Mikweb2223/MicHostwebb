<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $userId = requireLogin();
    $stmt = $pdo->prepare('SELECT u.id, u.role, u.email, u.first_name, u.last_name, u.hospital_name, COALESCE(NULLIF(u.phone, ""), h.phone) AS phone, u.blood_group, u.profile_image, u.date_of_birth, u.gender, COALESCE(NULLIF(u.state, ""), h.state) AS state, COALESCE(NULLIF(u.city, ""), h.city) AS city, COALESCE(NULLIF(u.address, ""), h.address) AS address, u.emergency_contact_name, u.emergency_phone, u.registration_number, u.operating_hours, u.created_at, h.verification_status FROM users u LEFT JOIN hospitals h ON h.user_id = u.id WHERE u.id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User profile not found.']);
        exit;
    }

    $user['id'] = (int)$user['id'];
    $user['name'] = $user['hospital_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    echo json_encode(['success' => true, 'user' => $user]);
} catch (Throwable $e) {
    error_log('Get profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load profile.']);
}
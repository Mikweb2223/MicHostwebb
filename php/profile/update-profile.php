<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireLogin();
    $role = strtolower(trim((string)($_POST['role'] ?? 'donor')));
    if (!in_array($role, ['donor', 'hospital'], true)) {
        throw new InvalidArgumentException('Invalid profile type.');
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }

    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $emailCheck->execute([$email, $userId]);
    if ($emailCheck->fetch()) {
        throw new InvalidArgumentException('That email address is already in use.');
    }

    $updates = [
        'email' => $email,
        'phone' => $phone,
        'state' => trim((string)($_POST['state'] ?? '')),
        'city' => trim((string)($_POST['city'] ?? '')),
        'address' => trim((string)($_POST['address'] ?? '')),
        'emergency_phone' => trim((string)($_POST['emergency_phone'] ?? '')),
        'role' => $role,
    ];

    if ($role === 'hospital') {
        $updates['hospital_name'] = trim((string)($_POST['hospital_name'] ?? ''));
        $updates['registration_number'] = trim((string)($_POST['registration_number'] ?? ''));
        $updates['operating_hours'] = trim((string)($_POST['operating_hours'] ?? ''));
        if ($updates['hospital_name'] === '') {
            throw new InvalidArgumentException('Hospital name is required.');
        }
    } else {
        $updates['first_name'] = trim((string)($_POST['first_name'] ?? ''));
        $updates['last_name'] = trim((string)($_POST['last_name'] ?? ''));
        $updates['blood_group'] = trim((string)($_POST['blood_group'] ?? ''));
        $updates['date_of_birth'] = trim((string)($_POST['date_of_birth'] ?? '')) ?: null;
        $updates['gender'] = trim((string)($_POST['gender'] ?? ''));
        $updates['emergency_contact_name'] = trim((string)($_POST['emergency_contact_name'] ?? ''));
        if ($updates['first_name'] === '' || $updates['last_name'] === '') {
            throw new InvalidArgumentException('Please enter your first and last name.');
        }
    }

    $profileImage = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['profile_image'];
        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Choose an image smaller than 5 MB.');
        }

        $imageInfo = getimagesize($upload['tmp_name']);
        $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$imageInfo || !isset($extensions[$imageInfo[2]])) {
            throw new InvalidArgumentException('Choose a JPG, PNG, GIF, or WebP image.');
        }

        $uploadDirectory = dirname(__DIR__, 2) . '/uploads/profiles';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Unable to prepare profile image storage.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$imageInfo[2]];
        if (!move_uploaded_file($upload['tmp_name'], $uploadDirectory . '/' . $fileName)) {
            throw new RuntimeException('Unable to save the profile image.');
        }
        $profileImage = 'uploads/profiles/' . $fileName;
        $updates['profile_image'] = $profileImage;
    }

    $setClause = implode(', ', array_map(static fn($column) => "{$column} = ?", array_keys($updates)));
    $update = $pdo->prepare("UPDATE users SET {$setClause} WHERE id = ?");
    $update->execute([...array_values($updates), $userId]);

    if ($role === 'hospital') {
        $hospital = $pdo->prepare('INSERT INTO hospitals (user_id, hospital_name, address, city, state, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hospital_name = VALUES(hospital_name), address = VALUES(address), city = VALUES(city), state = VALUES(state), phone = VALUES(phone), email = VALUES(email)');
        $hospital->execute([$userId, $updates['hospital_name'], $updates['address'], $updates['city'], $updates['state'], $phone, $email]);
    }

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.', 'profile_image' => $profileImage]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Update profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save profile.']);
}
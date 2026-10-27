<?php
require_once __DIR__ . '/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Accept form-encoded or multipart/form-data inputs
    $role = strtolower(trim((string)($_POST['role'] ?? $_REQUEST['role'] ?? 'donor')));
    $email = trim((string)($_POST['email'] ?? $_REQUEST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? $_REQUEST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? $_REQUEST['password'] ?? '');

    // Fields specific to donor
    $fullName = trim((string)($_POST['full_name'] ?? $_REQUEST['full_name'] ?? ''));
    $blood = trim((string)($_POST['blood_group'] ?? $_REQUEST['blood_group'] ?? ''));

    // Fields specific to hospital
    $hospitalName = trim((string)($_POST['hospital_name'] ?? $_REQUEST['hospital_name'] ?? ''));
    $address = trim((string)($_POST['address'] ?? $_REQUEST['address'] ?? ''));
    $license = trim((string)($_POST['license_number'] ?? $_REQUEST['license_number'] ?? ''));

    if ($email === '' || $phone === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email, phone and password are required.']);
        exit;
    }

    if (!isStrongPassword($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.']);
        exit;
    }

    if ($role === 'donor' && ($fullName === '' || $blood === '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Full name and blood group are required for donors.']);
        exit;
    }

    if ($role === 'hospital' && ($hospitalName === '' || $address === '' || $license === '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Hospital name, address and license number are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }

    // Check email uniqueness
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered.']);
        exit;
    }

    $first = '';
    $last = '';
    if ($role === 'donor') {
        $parts = preg_split('/\s+/', $fullName, 2);
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? '';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Handle optional profile image upload.
    $profileImage = null;
    $upload = $_FILES['profile_image'] ?? null;
    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        $maxImageSize = 5 * 1024 * 1024;
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The image upload failed.']);
            exit;
        }
        if ($upload['size'] > $maxImageSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Image must be 5 MB or smaller.']);
            exit;
        }
        if (!is_uploaded_file($upload['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image upload.']);
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload['tmp_name']);
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        if (!isset($allowedTypes[$mime])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Image must be a JPG or PNG file.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../../image';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $safe = bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mime];
        $target = $uploadDir . '/' . $safe;
        if (move_uploaded_file($upload['tmp_name'], $target)) {
            $profileImage = 'image/' . $safe;
        }
    }

    // Insert user
    $roleToInsert = $role === 'hospital' ? 'hospital' : 'donor';
    $insert = $pdo->prepare('INSERT INTO users (role, email, password_hash, first_name, last_name, phone, blood_group, hospital_name, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$roleToInsert, $email, $hash, $first, $last, $phone, $blood, ($role === 'hospital' ? $hospitalName : null), $profileImage]);
    $userId = (int)$pdo->lastInsertId();

    if ($role === 'hospital') {
        // Insert hospital details
        $hstmt = $pdo->prepare('INSERT INTO hospitals (user_id, hospital_name, address, phone, email, verification_status) VALUES (?, ?, ?, ?, ?, ?)');
        $hstmt->execute([$userId, $hospitalName, $address, $phone, $email, 'pending']);
    }

    echo json_encode(['success' => true, 'message' => 'Registration successful. Please log in.']);
} catch (Throwable $e) {
    error_log('Register error: ' . $e->getMessage());
    http_response_code(500);
    if (defined('DEV_MODE') && DEV_MODE) {
        echo json_encode(['success' => false, 'message' => 'Unable to register.', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unable to register.']);
    }
}

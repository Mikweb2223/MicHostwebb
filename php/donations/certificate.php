<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $userId = requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid donation id.';
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, donor_name, blood_group, units, donated_at, hospital_name FROM donations WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, $userId]);
    $donation = $stmt->fetch();
    if (!$donation) {
        http_response_code(404);
        echo 'Donation not found.';
        exit;
    }

    $filename = 'donation_certificate_' . $donation['id'] . '.html';
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Simple printable certificate HTML
    $donorName = htmlspecialchars($donation['donor_name'] ?? 'Donor');
    $hospital = htmlspecialchars($donation['hospital_name'] ?? '');
    $blood = htmlspecialchars($donation['blood_group'] ?? '');
    $units = htmlspecialchars((string)($donation['units'] ?? ''));
    $date = htmlspecialchars($donation['donated_at'] ?? $donation['created_at'] ?? date('Y-m-d'));

    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Donation Certificate</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:30px} .card{border:2px solid #e53935;padding:24px;border-radius:8px} h1{color:#e53935} .meta{margin-top:12px}</style>
    </head><body>
    <div class=\"card\">
    <h1>Donation Certificate</h1>
    <p>This certificate is presented to <strong>{$donorName}</strong> in recognition of a blood donation.</p>
    <div class=\"meta\">
    <p><strong>Hospital:</strong> {$hospital}</p>
    <p><strong>Blood Group:</strong> {$blood}</p>
    <p><strong>Units:</strong> {$units}</p>
    <p><strong>Date:</strong> {$date}</p>
    </div>
    <p style=\"margin-top:24px;\">Thank you for your life-saving donation.</p>
    </div>
    </body></html>";

} catch (Throwable $e) {
    http_response_code(500);
    error_log('Certificate error: ' . $e->getMessage());
    echo 'Unable to generate certificate.';
}

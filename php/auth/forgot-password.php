<?php
require_once __DIR__ . '/../config/bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer-7.1.1/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer-7.1.1/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer-7.1.1/src/SMTP.php';

header('Content-Type: application/json; charset=utf-8');

function sendPasswordResetEmail(string $toEmail, string $resetUrl): bool
{
    $smtpConfig = [];
    $smtpConfigFile = __DIR__ . '/../config/smtp.php';
    if (is_file($smtpConfigFile)) {
        $smtpConfig = require $smtpConfigFile;
        if (!is_array($smtpConfig)) {
            $smtpConfig = [];
        }
    }

    $smtpHost = (string) ($smtpConfig['host'] ?? getenv('SMTP_HOST') ?: '');
    $smtpUser = (string) ($smtpConfig['username'] ?? getenv('SMTP_USER') ?: '');
    $smtpPass = (string) ($smtpConfig['password'] ?? getenv('SMTP_PASS') ?: '');
    $smtpPort = (int) ($smtpConfig['port'] ?? getenv('SMTP_PORT') ?: '587');
    $smtpSecure = (string) ($smtpConfig['secure'] ?? getenv('SMTP_SECURE') ?: 'tls');
    $fromEmail = (string) ($smtpConfig['from_email'] ?? $smtpUser);
    $fromName = (string) ($smtpConfig['from_name'] ?? 'MicHost');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail ?: $smtpUser, $fromName);
        $mail->addAddress($toEmail);
        $mail->Subject = 'MicHost password reset';
        $mail->Body = "Hello,\n\nYou requested a password reset for your MicHost account.\n\nReset your password here:\n$resetUrl\n\nIf you did not request this, you can ignore this message.\n\nThanks,\nMicHost";
        $mail->AltBody = "Hello,\n\nYou requested a password reset for your MicHost account.\n\nReset your password here:\n$resetUrl";
        return $mail->send();
    } catch (Exception $e) {
        error_log('SMTP reset email failed: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? $_REQUEST['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists for this email, a reset link has been generated.'
    ]);
    exit;
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 3600);

$update = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?');
$update->execute([$token, $expiresAt, $user['id']]);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$resetUrl = $scheme . '://' . $host . '/reset-password.html?token=' . urlencode($token);
$mailSent = sendPasswordResetEmail($email, $resetUrl);

if (!$mailSent) {
    echo json_encode([
        'success' => true,
        'message' => 'Password reset link created. Email delivery may not be configured on this server, so use the link below instead.',
        'token' => $token,
        'reset_url' => $resetUrl,
        'mail_sent' => false
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'A password reset link has been sent to your email.',
    'token' => $token,
    'reset_url' => $resetUrl,
    'mail_sent' => true
]);

<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    } catch (Exception $e) {
        // ignore if dotenv not installed or failed
        error_log('Dotenv load error: ' . $e->getMessage());
    }
}

// Helper to read and normalize env vars (supports getenv and $_ENV).
function get_env_var($k) {
    $v = getenv($k);
    if ($v === false && isset($_ENV[$k])) $v = $_ENV[$k];
    if ($v === false || $v === null) return null;
    $v = trim($v);
    // strip surrounding single/double quotes if present
    if ((strlen($v) >= 2) && (($v[0] === '"' && $v[strlen($v)-1] === '"') || ($v[0] === "'" && $v[strlen($v)-1] === "'"))) {
        $v = substr($v, 1, -1);
    }
    return $v;
}

function gui_email(String $email_nhan, String $tieu_de_mail, String $noi_dung_mail) {
    $mailUsername = get_env_var('MAIL_USERNAME');
    $mailPassword = get_env_var('MAIL_PASSWORD');

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mailHost = get_env_var('MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->Host = $mailHost;
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $mail->Password = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = get_env_var('MAIL_PORT') ? (int)get_env_var('MAIL_PORT') : 587;

        $fromAddress = get_env_var('MAIL_FROM_ADDRESS') ?: 'no-reply@example.com';
        $fromName = get_env_var('MAIL_FROM_NAME') ?: 'StudyShare';
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($email_nhan);

        $mail->isHTML(true);
        $mail->Subject = $tieu_de_mail;
        $mail->Body = $noi_dung_mail;

        $mail->send();
        return true;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        error_log('Mail send failed: ' . $msg);
        $GLOBALS['send_mail_last_error'] = $msg;
        return false;
    }
}
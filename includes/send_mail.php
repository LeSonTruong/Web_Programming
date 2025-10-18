<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $to, string $subject, string $body, array $opts = []): bool
{
	// Create PHPMailer instance
	$mail = new PHPMailer(true);

	try {
		// Server settings
		$mail->isSMTP();
	    $mail->Host       = $opts['host'] ?? getenv('MAIL_HOST') ?: 'smtp.gmail.com';
		$mail->SMTPAuth   = true;
		$mail->Username   = $opts['username'] ?? getenv('MAIL_USERNAME');
		$mail->Password   = $opts['password'] ?? getenv('MAIL_PASSWORD');
	    $mail->SMTPSecure = $opts['secure'] ?? getenv('MAIL_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
	    $mail->Port       = $opts['port'] ?? (getenv('MAIL_PORT') ?: 587);

		// Optional: allow setting a different from address via env or opts
		$fromAddress = $opts['from'] ?? getenv('MAIL_FROM_ADDRESS') ?: getenv('MAIL_USERNAME') ?: 'noreply@example.com';
		$fromName    = $opts['from_name'] ?? getenv('MAIL_FROM_NAME') ?: 'StudyShare';

		if (!empty($opts['debug'])) {
			$mail->SMTPDebug = SMTP::DEBUG_SERVER;
			$mail->Debugoutput = function($str, $level) {
				error_log(sprintf('PHPMailer debug [%s]: %s', $level, $str));
			};
		}

		$mail->setFrom($fromAddress, $fromName);
		$mail->addAddress($to);

		// Content
		$mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body    = $body;

		$mail->send();
		return true;
	} catch (Exception $e) {
		// Log server-side, but don't echo to users
		error_log(message: sprintf('Mail send failed: %s; PHPMailer error: %s', $e->getMessage(), $mail->ErrorInfo ?? ''));
		return false;
	}
}
function sendMail($to, $subject, $body)
{
$mail = new PHPMailer(true);

try {
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = getenv('MAIL_USERNAME');
$mail->Password = getenv('MAIL_PASSWORD');
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('lesontruong.official@gmail.com', 'Document System');
$mail->addAddress($to);

$mail->isHTML(true);
$mail->Subject = $subject;
$mail->Body = $body;

if(!$mail->send()) {
echo 'Mailer Error: ' . $mail->ErrorInfo;
return false;
} else {
return true;
}
} catch (Exception $e) {
echo "Exception: " . $e->getMessage();
return false;
}
}
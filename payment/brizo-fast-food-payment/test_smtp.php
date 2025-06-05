<?php
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$mail = new PHPMailer(true);
$mail->SMTPDebug = 2;
$mail->isSMTP();
$mail->Host = $_ENV['SMTP_HOST'];
$mail->SMTPAuth = true;
$mail->Username = $_ENV['SMTP_USERNAME'];
$mail->Password = $_ENV['SMTP_PASSWORD'];
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = (int)$_ENV['SMTP_PORT'];

$mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
$mail->addAddress('your.test.email@example.com');
$mail->Subject = 'SMTP Test';
$mail->Body = 'This is a test email from your server';

try {
    $mail->send();
    echo 'Email sent successfully';
} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}
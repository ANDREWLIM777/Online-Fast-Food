<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../../vendor/autoload.php';

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'brizo.fastfood@gmail.com'; // Replace with your Gmail
    $mail->Password = 'abcdefghijklmnop'; // Replace with App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        file_put_contents('smtp_test.log', date('Y-m-d H:i:s') . " [$level]: $str\n", FILE_APPEND);
    };

    $mail->setFrom('brizo.fastfood@gmail.com', 'Brizo Test');
    $mail->addAddress('andrewlim8756@gmail.com');
    $mail->isHTML(false);
    $mail->Subject = 'SMTP Test from Brizo';
    $mail->Body = 'This is a test email to verify SMTP settings.';

    $mail->send();
    echo "Test email sent successfully!";
} catch (Exception $e) {
    echo "Test failed: {$mail->ErrorInfo}";
}
?>
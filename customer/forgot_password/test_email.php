<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\apache\bin\htdocs\Online-Fast-Food\php_errors.log');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;
require '../../vendor/autoload.php';

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('C:\xampp\apache\bin\htdocs\Online-Fast-Food\smtp_debug.log', $str . PHP_EOL, FILE_APPEND);
        };
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Test Email';
        $mail->Body = '<h3>Test Email</h3><p>This is a test email.</p>';
        // Temporary SSL workaround
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->send();
        $message = 'Email sent successfully';
    } catch (Exception $e) {
        $message = 'Failed to send email: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Email</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h4>Test Email Sending</h4>
        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Send Test Email</button>
        </form>
        <?php if ($message) { ?>
            <p class="<?php echo strpos($message, 'successfully') !== false ? 'text-success' : 'text-danger'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php } ?>
    </div>
</body>
</html>
<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/Online-Fast-Food/php_errors.log');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

// Set JSON header immediately
header('Content-Type: application/json; charset=UTF-8');

// Start output buffering
ob_start();
session_start();
require_once("../db_connect.php");
require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Initialize response
$response = ['status' => 'error', 'message' => 'Something went wrong'];

// Load .env file
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} catch (Exception $e) {
    error_log('Dotenv error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Configuration error']);
    ob_end_clean();
    exit;
}

try {
    error_log('forgot_process.php: Processing request for email: ' . ($_POST['email'] ?? 'not set'));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email exists in customers table
    $query = "SELECT id FROM customers WHERE email = ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('Database query preparation failed: ' . $conn->error);
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        throw new Exception('Database query execution failed: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Email not registered');
    }
    $customer = $result->fetch_assoc();
    $stmt->close();

    // Generate and store OTP
    $otp = random_int(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Delete old OTPs for this email
    $delete_query = "DELETE FROM otp_verification WHERE email = ?";
    $delete_stmt = $conn->prepare($delete_query);
    if ($delete_stmt === false) {
        throw new Exception('Failed to prepare OTP deletion query: ' . $conn->error);
    }
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Store new OTP
    $query = "INSERT INTO otp_verification (email, otp, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('Database query preparation failed: ' . $conn->error);
    }
    $stmt->bind_param("sss", $email, $otp, $expires_at);
    if (!$stmt->execute()) {
        throw new Exception('Failed to store OTP: ' . $stmt->error);
    }
    $stmt->close();

    // Send OTP via email (match test_smtp.php)
    $mail = new PHPMailer(true);
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
    $mail->Subject = 'Your OTP for Password Reset';
    $mail->Body = "
        <h3>Brizo Fast Food - Password Reset</h3>
        <p>Your OTP for password reset is: <strong>$otp</strong></p>
        <p>This OTP is valid for 5 minutes.</p>
        <p>If you did not request this, please ignore this email.</p>
    ";

    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        file_put_contents('C:/xampp/htdocs/Online-Fast-Food/smtp_debug.log', date('Y-m-d H:i:s') . ' [' . $level . '] ' . $str . PHP_EOL, FILE_APPEND);
    };
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    if (!$mail->send()) {
        throw new Exception('Failed to send OTP email: ' . $mail->ErrorInfo);
    }

    $_SESSION['reset_email'] = $email;
    session_write_close();
    error_log('Session reset_email set to: ' . $email);
    $response['status'] = 'success';
    $response['message'] = 'OTP sent to your email';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error in forgot_process.php: ' . $e->getMessage());
}

$conn->close();

// Log and discard stray output
$output = ob_get_contents();
if ($output !== '') {
    error_log('Stray output in forgot_process.php: ' . $output);
}
ob_end_clean();

echo json_encode($response);
?>
<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\Online-Fast-Food\php_errors.log');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

ob_start();
session_start(); // Ensure session is started
require_once("../db_connect.php");
require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load .env file
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} catch (Exception $e) {
    error_log('Dotenv error: ' . $e->getMessage());
    exit(json_encode(['status' => 'error', 'message' => 'Configuration error']));
}

header('Content-Type: application/json; charset=UTF-8');
$response = ['status' => 'error', 'message' => 'Something went wrong'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email exists in customers table
    $query = "SELECT * FROM customers WHERE email = ?";
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

    // Generate OTP
    $otp = random_int(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Store OTP
    $query = "INSERT INTO otp_verification (email, otp, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('Database query preparation failed: ' . $conn->error);
    }
    $stmt->bind_param("sss", $email, $otp, $expires_at);
    if (!$stmt->execute()) {
        throw new Exception('Failed to store OTP: ' . $stmt->error);
    }

    // Send OTP via email
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        file_put_contents('C:\xampp\htdocs\Online-Fast-Food\smtp_debug.log', date('Y-m-d H:i:s') . ' [' . $level . '] ' . $str . PHP_EOL, FILE_APPEND);
    };
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'andrewbrizo31@gmail.com';
    $mail->Password = 'xlmbznbzxczqefgn';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom('andrewbrizo31@gmail.com', 'Brizo Fast Food Melaka');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your OTP for Password Reset';
    $mail->Body = "
        <h3>Brizo Fast Food - Password Reset</h3>
        <p>Your OTP for password reset is: <strong>$otp</strong></p>
        <p>This OTP is valid for 5 minutes.</p>
        <p>If you did not request this, please ignore this email.</p>
    ";

    // Use CA bundle for secure connection
    $mail->SMTPOptions = [
        'ssl' => [
            'cafile' => 'C:\xampp\cacert.pem',
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]
    ];

    $mail->send();
    $_SESSION['reset_email'] = $email; // Store email in session
    session_write_close(); // Ensure session data is saved
    error_log('Session reset_email set to: ' . $email); // Debug session
    $response['status'] = 'success';
    $response['message'] = 'OTP sent to your email';

} catch (Exception $e) {
    $response['message'] = 'Failed to send email: ' . $e->getMessage();
    error_log('Error in forgot_process.php: ' . $e->getMessage());
}

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();

$output = ob_get_contents();
if ($output !== '') {
    error_log('Stray output in forgot_process.php: ' . $output);
}
ob_end_clean();

echo json_encode($response);
?>
<?php
   // Ensure no output before this
   ini_set('display_errors', 0);
   ini_set('log_errors', 1);
   ini_set('error_log', 'C:\xampp\apache\bin\htdocs\Online-Fast-Food\php_errors.log');
   error_reporting(E_ALL);
   date_default_timezone_set('Asia/Shanghai');

   ob_start();
   require_once("../db_connect.php");
   use PHPMailer\PHPMailer\PHPMailer;
   use PHPMailer\PHPMailer\Exception;
   use Dotenv\Dotenv;

   require '../../vendor/autoload.php';

   // Load .env file
   try {
       $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
       $dotenv->load();
   } catch (Exception $e) {
       error_log('Dotenv error: ' . $e->getMessage());
       exit('Configuration error');
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
       $mail->Subject = 'Your OTP for Password Reset';
       $mail->Body = "
           <h3>Brizo Fast Food - Password Reset</h3>
           <p>Your OTP for password reset is: <strong>$otp</strong></p>
           <p>This OTP is valid for 5 minutes.</p>
           <p>If you did not request this, please ignore this email.</p>
       ";
       $mail->send();
       $response['status'] = 'success';
       $response['message'] = 'OTP sent to your email';

   } catch (Exception $e) {
       $response['message'] = $e->getMessage();
   }

   if (isset($stmt)) {
       $stmt->close();
   }
   $conn->close();

   // Capture any stray output
   $output = ob_get_contents();
   if ($output !== '') {
       error_log('Stray output in forgot_process.php: ' . $output);
   }
   ob_end_clean();

   echo json_encode($response);
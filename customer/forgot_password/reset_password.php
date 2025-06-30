<?php
ob_start();
require_once("../db_connect.php");
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/Online-Fast-Food/php_errors.log');
error_reporting(E_ALL);

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_SESSION['reset_email']) ? filter_var($_SESSION['reset_email'], FILTER_SANITIZE_EMAIL) : '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    error_log('reset_password.php: Session reset_email = ' . ($email ?? 'not set'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid or missing email. Please restart the password reset process.";
    } elseif (empty($password) || empty($confirm_password)) {
        $error = "Please enter and confirm your new password.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update password in the database
        $query = "UPDATE customers SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
            error_log('reset_password.php: Database error - ' . $conn->error);
        } else {
            $stmt->bind_param("ss", $hashed_password, $email);
            if ($stmt->execute()) {
                // Insert notification
                $title = "Password Reset Successful";
                $message = "Your password has been reset. Contact support if you didn’t initiate this.";
                $type = "announcement";
                $stmt = $conn->prepare("INSERT INTO customer_notifications (customer_id, title, message, type, created_at) 
                                       SELECT id, ?, ?, ?, NOW() FROM customers WHERE email = ?");
                $stmt->bind_param("ssss", $title, $message, $type, $email);
                $stmt->execute();
                $success = "Password reset successfully. Redirecting to login...";
                unset($_SESSION['reset_email']);
                session_write_close();
                error_log('reset_password.php: Password reset successful, redirecting to login.php');
                // Add meta refresh for brief success message display
                echo "<meta http-equiv='refresh' content='2;url=http://localhost/Online-Fast-Food/customer/login.php?success=" . urlencode($success) . "'>";
                // Ensure no further output interferes
                ob_end_flush();
            } else {
                $error = "Failed to update password: " . $stmt->error;
                error_log('reset_password.php: Failed to update password - ' . $stmt->error);
            }
            $stmt->close();
        }
    }
    $conn->close();
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Brizo Fast Food</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Fredoka', sans-serif;
            background: linear-gradient(to right, #ffe259, #ffa751);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.8s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form_btn {
            background: linear-gradient(to right, #ffa751, #ffe259);
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            color: white;
            font-weight: bold;
            transition: 0.3s ease;
        }
        .form_btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        .form-control {
            border-radius: 30px;
            border: 2px solid #ffe259;
        }
        .error {
            color: red;
            font-size: 0.9em;
        }
        .success {
            color: green;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="login-box text-center">
        <img src="/Online-Fast-Food/customer/logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h4 class="mb-4"><i class="fas fa-lock"></i> Reset Password</h4>
        <?php if ($error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        <?php if ($success) { ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <p><a href="http://localhost/Online-Fast-Food/customer/login.php" style="color: #ffa751;">Go to Login</a></p>
        <?php } else { ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="btn form_btn btn-block">Reset Password</button>
            </form>
        <?php } ?>
        <hr>
        <p><a href="http://localhost/Online-Fast-Food/customer/login.php" style="color: #ffa751;">Back to Login</a></p>
    </div>
</body>
</html>
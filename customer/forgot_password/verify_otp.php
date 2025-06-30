<?php
require_once("../db_connect.php");
session_start();

error_log('verify_otp.php: Session reset_email = ' . ($_SESSION['reset_email'] ?? 'not set')); // Debug session

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_SESSION['reset_email']) ? filter_var($_SESSION['reset_email'], FILTER_SANITIZE_EMAIL) : '';
    $otp = filter_var($_POST['otp'], FILTER_SANITIZE_STRING);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid or missing email. Please request a new OTP.";
    } elseif (empty($otp)) {
        $error = "Please enter the OTP.";
    } else {
        $query = "SELECT * FROM otp_verification WHERE email = ? AND otp = ? AND expires_at > NOW()";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // OTP is valid, delete it
                $delete_query = "DELETE FROM otp_verification WHERE email = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("s", $email);
                $delete_stmt->execute();
                $delete_stmt->close();
                header("Location: reset_password.php");
                exit;
            } else {
                $error = "Invalid or expired OTP";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify OTP - Brizo Fast Food</title>
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
    </style>
</head>
<body>
    <div class="login-box text-center">
        <img src="/Online-Fast-Food/customer/logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h4 class="mb-4"><i class="fas fa-key"></i> Enter OTP</h4>
        <?php if ($error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        <form method="POST">
            <div class="form-group">
                <label for="otp">OTP</label>
                <input type="text" name="otp" id="otp" class="form-control" placeholder="Enter OTP" required>
            </div>
            <button type="submit" class="btn form_btn btn-block">Verify OTP</button>
        </form>
        <hr>
        <p><a href="forgot_password.php" style="color: #ffa751;">Resend OTP</a></p>
    </div>
</body>
</html>
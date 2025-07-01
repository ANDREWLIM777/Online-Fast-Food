<?php
require_once '../Admin_Account/db.php';
session_start();

if (!isset($_GET['email']) || !filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    header("Location: forgot_password.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
    $otp = filter_var($_POST['otp'], FILTER_SANITIZE_STRING);

    if (!preg_match('/^\d{6}$/', $otp)) {
        $error = "OTP must be a 6-digit number.";
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
                $_SESSION['reset_email'] = $email;
                $delete_query = "DELETE FROM otp_verification WHERE email = ?";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param("s", $email);
                $delete_stmt->execute();
                $delete_stmt->close();
                header("Location: reset_password.php");
                exit;
            } else {
                $error = "Invalid or expired OTP.";
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta Dummy Data name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP Verification - Brizo Fast Food Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Fredoka', sans-serif;
            background: #0c0a10;
            color: #eee;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .container {
            max-width: 500px;
            margin: auto;
            background: #1a1a1a;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
        h2 {
            color: #c0a23d;
            text-align: center;
            margin-bottom: 25px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            color: #fff;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #c0a23d;
            color: #000;
            font-weight: bold;
            border-radius: 8px;
            border: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        .error {
            background: #4e1e1e;
            color: #f88;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .back-btn {
            margin-top: 15px;
            display: inline-block;
            color: #c0a23d;
            text-decoration: none;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body>
    <div class="container text-center">
        <img src="/Online-Fast-Food/customer/logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h2>Enter OTP</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="otp">Enter 6-digit OTP</label>
                <input type="text" name="otp" id="otp" class="form-control" placeholder="Enter OTP" maxlength="6" required>
            </div>
            <button type="submit" class="btn">Verify</button>
        </form>
        <a href="forgot_password.php" class="back-btn"><i class="fas fa-redo"></i> Resend OTP</a><br>
        <a href="login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login Page</a>
    </div>
</body>
</html>
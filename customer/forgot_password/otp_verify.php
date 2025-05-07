<?php
session_start();

// Redirect if email not set
if (!isset($_SESSION['otp_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$otp_error = '';
$success = '';

// For demo: static OTP
$expectedOtp = '123456';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);

    if ($otp !== $expectedOtp) {
        $otp_error = "❌ Invalid OTP. Please try again.";
    } else {
        $_SESSION['otp_verified'] = true;
        header("Location: reset_password.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Verify OTP</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
        background: linear-gradient(to right,rgb(255, 211, 89),rgb(255, 139, 81));
        color: #eee;
      font-family: 'Segoe UI', sans-serif;
      padding: 40px 20px;
    }

    .container {
      max-width: 500px;
      margin: auto;
      background: #1a1a1a;
      padding: 60px;
      border-radius: 14px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.4);
    }

    h2 {
      color: #c0a23d;
      text-align: center;
      margin-bottom: 25px;
    }

    input[type="text"] {
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
      cursor: pointer;
      margin-top: 15px;
    }

    .error {
      background: #4e1e1e;
      color: #f88;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
    }

    .success {
      background: #1e4e2e;
      color: #a5f8b8;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
    }

    .back-btn {
      margin-top: 20px;
      display: inline-block;
      color: #c0a23d;
      text-decoration: none;
    }

    .back-btn i {
      margin-right: 6px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Verify OTP</h2>

    <?php if ($otp_error): ?>
      <div class="error"><?= htmlspecialchars($otp_error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label>Enter the 6-digit OTP sent to your email</label>
      <input type="text" name="otp" maxlength="6" required>
      <button type="submit" class="btn">Verify</button>
    </form>

    <a href="/Online-Fast-Food/customer/forgot_password/forgot_password.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>

  </div>
</body>
</html>

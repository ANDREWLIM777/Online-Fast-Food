<?php
session_start();
if (!isset($_SESSION['otp_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);
    if ($otp === '123456') {
        header("Location: reset_password.php");
        exit;
    } else {
        $error = "❌ Invalid OTP. Try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>OTP Verification</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { background: #0c0a10; color: #eee; font-family: 'Segoe UI'; padding: 40px 20px; }
    .container { max-width: 500px; margin: auto; background: #1a1a1a; padding: 30px; border-radius: 14px; box-shadow: 0 6px 20px rgba(0,0,0,0.4); }
    h2 { color: #c0a23d; text-align: center; margin-bottom: 25px; }
    input[type="text"] { width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: #fff; }
    .btn { width: 100%; padding: 12px; background: #c0a23d; color: #000; font-weight: bold; border-radius: 8px; cursor: pointer; }
    .error { background: #4e1e1e; color: #f88; padding: 10px; border-radius: 8px; margin-bottom: 15px; }
    .back-btn { margin-top: 15px; display: inline-block; color: #c0a23d; text-decoration: none; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Enter OTP</h2>
    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <form method="POST">
      <label>Enter 6-digit OTP</label>
      <input type="text" name="otp" maxlength="6" required>
      <br><br>
      <button class="btn">Verify</button>
    </form>
    <a href="login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login Page</a>
  </div>
</body>
</html>

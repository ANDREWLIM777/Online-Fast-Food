<?php
require '../Admin_Account/db.php';
session_start();

$email = $_SESSION['otp_email'] ?? '';
$error = '';
$success = '';

if (empty($email)) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        $error = "❌ Passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "❌ Password too short.";
    } else {
        $hashed = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed, $email);
        $stmt->execute();
        $success = "✅ Password reset successfully.";
        unset($_SESSION['otp_email']);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Reset Password</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { background: #0c0a10; color: #eee; font-family: 'Segoe UI'; padding: 40px 20px; }
    .container { max-width: 500px; margin: auto; background: #1a1a1a; padding: 30px; border-radius: 14px; box-shadow: 0 6px 20px rgba(0,0,0,0.4); }
    h2 { color: #c0a23d; text-align: center; margin-bottom: 25px; }
    input[type="password"] { width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: #fff; }
    .btn { width: 100%; padding: 12px; background: #c0a23d; color: #000; font-weight: bold; border-radius: 8px; cursor: pointer; }
    .error, .success { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
    .error { background: #4e1e1e; color: #f88; }
    .success { background: #1e4e28; color: #9f9; }
    .back-btn { margin-top: 15px; display: inline-block; color: #c0a23d; text-decoration: none; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Reset Password</h2>
    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
    <form method="POST">
      <label>New Password</label>
      <input type="password" name="new_password" required><br><br>
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" required><br><br>
      <button class="btn">Save Password</button>
    </form>
    <a href="change_pass.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Change Password</a>
  </div>
</body>
</html>

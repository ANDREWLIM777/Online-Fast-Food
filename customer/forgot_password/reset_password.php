<?php
require '../db_connect.php';
session_start();

if (!isset($_SESSION['otp_email']) || !isset($_SESSION['otp_verified'])) {
    header("Location: forgot_password.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if (strlen($password) < 8) {
        $error = "⚠️ Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "❌ Passwords do not match.";
    } else {
        $email = $_SESSION['otp_email'];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);

        $stmt->execute();

        session_unset(); session_destroy();
        $success = "✅ Password reset successfully. You can now <a href='../login.php'>login</a>.";
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
    label { display: block; margin-top: 15px; margin-bottom: 6px; font-weight: bold; }
    input[type="password"] {
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
      margin-top: 20px;
    }
    .error, .success {
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
    }
    .error { background: #4e1e1e; color: #f88; }
    .success { background: #1e4e2e; color: #a5f8b8; }
    .success a { color: #a5f8b8; text-decoration: underline; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Reset Your Password</h2>

    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <label>New Password</label>
      <input type="password" name="password" required>

      <label>Confirm Password</label>
      <input type="password" name="confirm" required>

      <button type="submit" class="btn">Update Password</button>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>

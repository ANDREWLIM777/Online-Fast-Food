<?php
require '../db_connect.php'; // use your actual path
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $error = "❌ This email is not registered.";
    } else {
        $_SESSION['otp_email'] = $email;
        header("Location: otp_verify.php"); // or your desired verification step
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password</title>
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
      height: auto;
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

    label {
      display: block;
      font-weight: bold;
      margin-bottom: 6px;
    }

    input[type="email"] {
      width: 95%;
      padding: 12px;
      background: #2a2a2a;
      border: 1px solid #444;
      border-radius: 8px;
      color: #fff;
    }

    .btn {
      width: 100%;
      padding: 12px;
      background:rgb(255, 189, 75);
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
    <h2>Forgot Password?</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label>Enter your email</label>
      <input type="email" name="email" required>
      <button type="submit" class="btn">Send OTP</button>
    </form>

    <a href="/Online-Fast-Food/customer/login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
  </div>
</body>
</html>

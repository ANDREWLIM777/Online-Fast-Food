<!-- php/register.php -->
<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($fullname) || empty($email) || empty($password) || empty($phone)) {
        echo "All fields are required. <a href='../register.html'>Try again</a>";
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    if (!$check) {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        exit();
    }
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "Email is already registered. <a href='../register.html'>Try again</a>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO customers (fullname, email, password, phone) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        exit();
    }
    $stmt->bind_param("ssss", $fullname, $email, $hashedPassword, $phone);

    if ($stmt->execute()) {
        echo "Registration successful! <a href='../login.html'>Login here</a>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Brizo Fast Food Melaka - Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="login.css">
</head>
<script src="login.js"></script>

<body>
  <div class="login-container">
    <div class="logo">🍔 BRIZO FAST FOOD MELAKA</div>
    <h2>Welcome Back!</h2>
    <p class="subtitle">Please login to your customer account</p>

    <form action="login.php" method="POST">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="e.g. brizo@email.com" required>
      </div>

      <div class="form-group password-group">
  <label for="password">Password</label>
  <div class="password-wrapper">
    <input type="password" id="password" name="password" placeholder="••••••••" required>
    <span id="togglePassword" class="eye-icon">👁️</span>
  </div>
</div>

      <button type="submit">Login</button>
    </form>

    <p class="auth-link">Don't have an account? <a href="register.php">Register here</a></p>
  </div>
</body>
</html>

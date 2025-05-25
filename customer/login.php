<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=empty_fields");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, fullname, password FROM customers WHERE email = ?");
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $fullname, $hashedPassword);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $_SESSION['customer_id'] = $id;
            $_SESSION['customer_name'] = $fullname;
            unset($_SESSION['is_guest']); // ✅ Important: remove guest if login real account
            header("Location: ../customer/menu/menu.php");
            exit();
        } else {
            header("Location: login.php?error=wrong_password");
            exit();
        }
    } else {
        header("Location: login.php?error=email_not_found");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Brizo Fast Food Melaka - Login</title>
  <link rel="stylesheet" href="login.css">
</head>

<body>
<div class="login-container">

<?php if (isset($_GET['logout']) && $_GET['logout'] == 1): ?>
  <div class="alert-success">👋 You’ve been logged out. See you again soon!</div>
<?php endif; ?>

<div class="logo">
  <img src="logo.png" alt="Brizo Fast Food Melaka Logo" width="160" />
</div>

<h2>Welcome Back!</h2>
<p class="subtitle">Please login to your customer account</p>

<?php if (isset($_GET['error'])): ?>
<div class="error-box">
  <?php
    switch ($_GET['error']) {
      case 'empty_fields': echo "Please fill in all fields."; break;
      case 'email_not_found': echo "Email not found. Try registering."; break;
      case 'wrong_password': echo "Incorrect password. Please try again."; break;
      default: echo "Something went wrong.";
    }
  ?>
</div>
<?php endif; ?>

<form action="login.php" method="POST">
  <label for="email">Email Address</label>
  <input type="email" id="email" name="email" placeholder="e.g. brizo@email.com" required>

  <div class="form-group password-group">
    <label for="password">Password</label>
    <div class="password-wrapper">
      <input type="password" id="password" name="password" placeholder="••••••••" required>
      <span id="togglePassword" class="eye-icon">👁️</span>
    </div>
  </div>

  <button type="submit">🍟 Login</button>
</form>

<!-- ✅ Guest Login Button (Below Login button) -->
<div class="guest-login">
  <form action="../customer/manage_account/guest/guest_login.php" method="POST">
    <button type="submit" class="guest-btn">Continue as Guest</button>
  </form>
</div>

<!-- Add under login form -->
<a href="/Online-Fast-Food/customer/forgot_password/forgot_password.php" class="forgot-link">Forgot your password?</a>


<!-- ✅ Register link -->
<p>Don't have an account? <a href="register.php">Register here</a></p>

<div class="admin-login-link">
  <p>Are you an admin?</p>
  <a href="/Online-Fast-Food/Admin/Admin_Account/login.php" class="admin-login-btn">Login here</a>
</div>

</div>

<script src="login.js"></script>
</body>
</html>

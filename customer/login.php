<?php
session_start();
require 'db_connect.php';

$email = '';
$password = '';
$errorCode = '';

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Preserve for repopulating
    $_SESSION['old_email'] = $email;

    if (empty($email) || empty($password)) {
        $errorCode = 'empty_fields';
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, password FROM customers WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $fullname, $hashedPassword);
                $stmt->fetch();

                if (password_verify($password, $hashedPassword)) {
                    $_SESSION['customer_id'] = $id;
                    $_SESSION['customer_name'] = $fullname;
                    unset($_SESSION['is_guest']);
                    unset($_SESSION['old_email']);
                    header("Location: ../customer/menu/menu.php");
                    exit();
                } else {
                    $errorCode = 'wrong_password';
                }
            } else {
                $errorCode = 'email_not_found';
            }
        } else {
            $errorCode = 'stmt_error';
        }
    }

    // Redirect back with error
    if ($errorCode !== '') {
        header("Location: login.php?error=$errorCode");
        exit();
    }
}

// Repopulate email if available
$oldEmail = $_SESSION['old_email'] ?? '';
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
      default: echo "Something went wrong. Please try again.";
    }
  ?>
</div>
<?php endif; ?>

<form action="login.php" method="POST">
  <label for="email">Email Address</label>
  <input type="email" id="email" name="email" required placeholder="e.g. brizo@email.com"
         value="<?= htmlspecialchars($oldEmail) ?>">

  <div class="form-group password-group">
    <label for="password">Password</label>
    <div class="password-wrapper">
      <input type="password" id="password" name="password" required placeholder="••••••••">
      <span id="togglePassword" class="eye-icon">👁️</span>
    </div>
  </div>

  <button type="submit">🍟 Login</button>
</form>

<div class="guest-login">
  <form action="../customer/manage_account/guest/guest_login.php" method="POST">
    <button type="submit" class="guest-btn">Continue as Guest</button>
  </form>
</div>

<a href="/Online-Fast-Food/customer/forgot_password/forgot_password.php" class="forgot-link">Forgot your password?</a>

<p>Don't have an account? <a href="register.php">Register here</a></p>

<div class="admin-login-link">
  <p>Are you an admin?</p>
  <a href="/Online-Fast-Food/Admin/Admin_Account/login.php" class="admin-login-btn">Login here</a>
</div>

</div>

<script>
  const toggle = document.getElementById('togglePassword');
  const pwd = document.getElementById('password');

  toggle.addEventListener('click', () => {
    const type = pwd.type === 'password' ? 'text' : 'password';
    pwd.type = type;
    toggle.textContent = type === 'password' ? '👁️' : '🙈';
  });
</script>
</body>
</html>

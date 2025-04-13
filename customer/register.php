<?php
require 'db_connect.php';

$showError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($fullname) || empty($email) || empty($password) || empty($phone)) {
        echo "All fields are required. <a href='register.html'>Try again</a>";
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
        echo "Email is already registered. <a href='register.html'>Try again</a>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO customers (fullname, email, password, phone) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        exit();
    }
    $stmt->bind_param("ssss", $fullname, $email, $hashedPassword, $phone);

    if ($stmt->execute()) {
        echo "Registration successful! <a href='login.php'>Login here</a>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Brizo Fast Food Melaka - Register</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="register.css">
</head>

<body>
  <div class="register-container">
    <div class="logo">🍔 BRIZO FAST FOOD MELAKA</div>
    <h2>Customer Registration</h2>

    <?php if ($showError): ?>
      <div class="error-box"><?= htmlspecialchars($showError) ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
      <label for="fullname">Full Name</label>
      <input type="text" id="fullname" name="fullname" placeholder="e.g. Brizo The BEST" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="e.g. brizo@email.com" required>

      <div class="form-group password-group">
        <label for="password">Password</label>
        <div class="password-wrapper">
        <input
  type="password"
  id="registerPassword"
  name="password"
  placeholder="••••••••"
  minlength="8"
  required
>

          <span id="toggleRegisterPassword" class="eye-icon">👁️</span>
        </div>
      </div>

      <label for="phone">Phone Number</label>
      <input type="text" id="phone" name="phone" placeholder="e.g. 012-3456 7890" required>

      <button type="submit">🍟 Register Now</button>
    </form>

    <p>Already have an account? <a href="login.php">Login here</a></p>
  </div>

  <script src="register.js"></script>
</body>
</html>

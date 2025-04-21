<?php
require 'db_connect.php';

$showError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($fullname) || empty($email) || empty($password) || empty($phone)) {
        $showError = "All fields are required.";
    } else
    
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        $showError = "Phone number must contain only digits (11 characters).";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $check = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        if (!$check) {
            $showError = "Database error (prepare failed).";
        } else {
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $showError = "⚠️ This email is already registered. Try logging in instead.";
            } else {
                $stmt = $conn->prepare("INSERT INTO customers (fullname, email, password, phone) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    $showError = "Database error (prepare failed).";
                } else {
                    $stmt->bind_param("ssss", $fullname, $email, $hashedPassword, $phone);
                    if ($stmt->execute()) {
                        header("Location: register-success.php");
                        exit();
                    } else {
                        $showError = "Something went wrong. Please try again.";
                    }
                }
            }
        }
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

  <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
  <div class="alert-success">
    ✅ Your account has been successfully deleted. Feel free to register a new one anytime!
  </div>
<?php endif; ?>

    <!-- Brizo Logo Image -->
    <div class="logo">
      <img src="logo.png" alt="Brizo Fast Food Melaka Logo" width="160" />
      <noscript><strong>🍔 BRIZO FAST FOOD MELAKA</strong></noscript>
    </div>

    <h2>Customer Registration</h2>

    <?php if ($showError): ?>
      <div class="error-box"><?= htmlspecialchars($showError) ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
      <label for="fullname">Full Name</label>
      <input type="text" id="fullname" name="fullname" placeholder="e.g. Brizo Is The BEST" required>

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
      <input 
  type="tel" 
  id="phone" 
  name="phone" 
  placeholder="e.g. 0123456789" 
  pattern="[0-9]{10,15}" 
  inputmode="numeric" 
  required
>


      <button type="submit">🍟 Register Now</button>
    </form>

    <p>Already have an account? <a href="login.php">Login here</a></p>
  </div>

  <script src="register.js"></script>
</body>
</html>

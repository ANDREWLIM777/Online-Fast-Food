<?php
require '../Admin_Account/db.php';
include '../auth.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Fetch the current hash password
    $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($current, $hashed)) {
        $error = "❌ Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $error = "❌ New passwords do not match.";
    } else {
        $new_hashed = password_hash($new, PASSWORD_BCRYPT);
        $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
        $update->bind_param("si", $new_hashed, $user_id);
        $update->execute();
        $success = "✅ Password successfully updated.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Password & Security</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      background: #0c0a10;
      color: #eee;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 40px 20px;
    }

body::after {
  content: '';
  position: fixed;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at 50% 50%, rgba(244, 227, 178, 0.07) 0%, transparent 70%);
  animation: auraPulse 8s infinite;
  pointer-events: none;
  z-index: -1;
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-image: 
    radial-gradient(circle at 20% 30%, rgba(244, 228, 178, 0.15) 1px, transparent 2px),
    radial-gradient(circle at 80% 70%, rgba(244, 228, 178, 0.15) 1px, transparent 2px);
  background-size: 60px 60px;
  animation: stardust 20s linear infinite;
  pointer-events: none;
  z-index: -2; 
}

@keyframes auraPulse {
  0% { transform: scale(0.8); opacity: 0.3; }
  50% { transform: scale(1.2); opacity: 0.08; }
  100% { transform: scale(0.8); opacity: 0.3; }
}

@keyframes stardust {
  0% { background-position: 0 0, 100px 100px; }
  100% { background-position: 100px 100px, 0 0; }
}

    .password-wrapper {
  position: relative;
}

.password-wrapper input {
  width: 100%;
  padding: 12px 10px 12px 12px;
  background: #2a2a2a;
  color: #fff;
  border: 1px solid #444;
  border-radius: 8px;
  font-size: 1rem;
}

.toggle-password {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #c0a23d;
  cursor: pointer;
  font-size: 1.1rem;
}


    .container {
      max-width: 600px;
      margin: 0 auto;
      background: #1a1a1a;
      padding: 40px;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    }

    h2 {
      color: #c0a23d;
      text-align: center;
      margin-bottom: 30px;
      font-size: 1.9rem;
      font-family: 'Playfair Display', serif;
      letter-spacing: 1px;
    }

    .form-group {
      margin-bottom: 30px;
      position: relative;
    }

    label {
      font-weight: 600;
      margin-bottom: 6px;
      display: block;
    }

    input[type="password"] {
      width: 100%;
      padding: 12px 10px 12px 12px;
      background: #2a2a2a;
      color: #fff;
      border: 1px solid #444;
      border-radius: 8px;
      font-size: 1rem;
    }

    .toggle-password {
      position: absolute;
      top: 50%;
      right: 5px;
      transform: translateY(-50%);
      color: #888;
      cursor: pointer;
      font-size: 1rem;
      z-index: 2;
    }

    .toggle-password:hover {
      color: #c0a23d;
    }

    .btn {
      background: #c0a23d;
      color: #000;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: bold;
      width: 100%;
      font-size: 1rem;
    }

    .btn:hover {
      background: #e0c066;
    }

    .error, .success {
      margin-bottom: 20px;
      padding: 12px;
      border-radius: 8px;
      font-weight: bold;
    }

    .error {
      background: #4e1e1e;
      color: #f88;
      border: 1px solid #a55;
    }

    .success {
      background: #1e4e28;
      color: #9f9;
      border: 1px solid #5a5;
    }

    .forgot-link {
      text-align: center;
      margin-top: 10px;
      color: #aaa;
      font-size: 0.95rem;
    }

    .forgot-link a {
      color: #f9d37c;
      text-decoration: underline;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: transparent;
      border: 2px solid #c0a23d;
      color: #c0a23d;
      padding: 10px 18px;
      border-radius: 30px;
      font-weight: bold;
      transition: all 0.3s ease;
      font-size: 0.95rem;
      text-decoration: none;
      margin-bottom: 25px;
    }

    .back-btn:hover {
      background: #c0a23d;
      color: #000;
    }

    .back-btn i {
      transition: transform 0.3s ease;
    }

    .back-btn:hover i {
      transform: translateX(-5px);
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="more.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to More Page</a>

    <h2>Password & Security Settings</h2>

    <?php if ($error): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">

    <div class="form-group">
  <label>Current Password</label>
  <div class="password-wrapper">
    <input type="password" name="current_password" id="currentPassword" required>
    <i class="fas fa-eye-slash toggle-password" onclick="togglePassword('currentPassword', this)"></i>
  </div>
</div>

<div class="form-group">
  <label>New Password</label>
  <div class="password-wrapper">
    <input type="password" name="new_password" id="newPassword" required>
    <i class="fas fa-eye-slash toggle-password" onclick="togglePassword('newPassword', this)"></i>
  </div>
</div>

<div class="form-group">
  <label>Confirm New Password</label>
  <div class="password-wrapper">
    <input type="password" name="confirm_password" id="confirmPassword" required>
    <i class="fas fa-eye-slash toggle-password" onclick="togglePassword('confirmPassword', this)"></i>
  </div>
</div>


      <button type="submit" class="btn">Save Changes</button>
    </form>

    <div class="forgot-link">
      <p>Forgot your password? <a href="forgot_password.php">Click here</a></p>
    </div>
  </div>

  <script>
function togglePassword(inputId, iconElement) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    iconElement.classList.remove('fa-eye-slash');
    iconElement.classList.add('fa-eye');
  } else {
    input.type = 'password';
    iconElement.classList.remove('fa-eye');
    iconElement.classList.add('fa-eye-slash');
  }
}
</script>


</body>
</html>
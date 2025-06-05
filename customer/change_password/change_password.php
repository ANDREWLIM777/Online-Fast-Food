<?php
session_start();
require '../db_connect.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($hashed);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($old, $hashed)) {
        $error = "❌ Old password is incorrect.";
    } elseif (strlen($new) < 8) {
        $error = "⚠️ New password must be at least 8 characters.";
    } elseif ($new !== $confirm) {
        $error = "❌ New passwords do not match.";
    } else {
        $newHashed = password_hash($new, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE customers SET password = ? WHERE id = ?");
        $update->bind_param("si", $newHashed, $customerId);
        $update->execute();

        $success = "✅ Password updated successfully.";
    }
}
?>

<!DOCTYPE html>
< lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password</title>
  <style>
    body {
      background: #fdf6e3;
      font-family: 'Segoe UI', sans-serif;
      padding: 40px 20px;
    }
    .container {
      max-width: 650px;
      margin: auto;
      background: #fff;
      padding: 2rem 2.5rem;
      border-radius: 14px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #d63031;
      margin-bottom: 1.5rem;
    }
    label {
      font-weight: bold;
      display: block;
      margin-top: 1rem;
    }
    .input-group {
      position: relative;
    }
    input[type="password"], input[type="text"] {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      margin-top: 0.3rem;
      font-size: 16px;
      background: white;
      color: #333;
    }
    .toggle-visibility {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 15px;
      user-select: none;
      color: #999;
    }
    .btn {
      width: 100%;
      margin-top: 2rem;
      padding: 12px;
      background: #d63031;
      color: white;
      font-weight: bold;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
    .btn:hover {
      background-color: #b72d2d;
    }
    .error, .success {
      margin-top: 1rem;
      padding: 12px;
      border-radius: 8px;
    }
    .error { background: #ffe0e0; color: #b00020; }
    .success { background: #dfeeea; color: #0c6b40; }

    .strength-bar {
      height: 8px;
      border-radius: 6px;
      background: #ddd;
      margin-top: 4px;
      overflow: hidden;
    }
    .strength-fill {
      height: 100%;
      width: 0%;
      transition: width 0.3s ease;
    }
    .strength-text {
      font-size: 0.85rem;
      margin-top: 4px;
      font-weight: bold;
      color: #444;
    }
    .match-indicator {
      margin-top: 4px;
      font-weight: bold;
      font-size: 0.9rem;
    }
    .back-button {
      display: inline-block;
      margin-top: 20px;
      text-align: center;
      background: #ffeaa7;
      color: #2d3436;
      padding: 10px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
      transition: background 0.3s ease;
    }
    .back-button:hover {
      background: #f6b93b;
      color: #000;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>🔐 Change Password</h2>

    <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" novalidate>
      <label for="old_password">Current Password</label>
      <div class="input-group">
        <input type="password" name="old_password" id="old_password" required>
        <span class="toggle-visibility" onclick="togglePassword('old_password', this)">👁️</span>
      </div>

      <label for="new_password">New Password</label>
      <div class="input-group">
        <input type="password" name="new_password" id="new_password" required oninput="updateStrength('new_password', 'strengthFill1', 'strengthText1')">
        <span class="toggle-visibility" onclick="togglePassword('new_password', this)">👁️</span>
      </div>
      <div class="strength-bar"><div id="strengthFill1" class="strength-fill"></div></div>
      <div id="strengthText1" class="strength-text"></div>

      <label for="confirm_password">Confirm Password</label>
      <div class="input-group">
        <input type="password" name="confirm_password" id="confirm_password" required 
               oninput="updateStrength('confirm_password', 'strengthFill2', 'strengthText2'); checkMatch()"
               onkeyup="checkMatch()">
        <span class="toggle-visibility" onclick="togglePassword('confirm_password', this)">👁️</span>
      </div>
      <div class="strength-bar"><div id="strengthFill2" class="strength-fill"></div></div>
      <div id="strengthText2" class="strength-text"></div>
      <div id="matchStatus" class="match-indicator"></div>

      <button class="btn" type="submit">Update Password</button>
    </form>
    <?php endif; ?>

    <a href="/Online-Fast-Food/customer/menu/menu.php" class="back-button">Back to Menu</a>
  </div>

  <script>
    function togglePassword(id, icon) {
      const input = document.getElementById(id);
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.textContent = input.type === 'password' ? '👁️' : '🙈';
    }

    function updateStrength(id, fillId, textId) {
      const pwd = document.getElementById(id).value;
      const fill = document.getElementById(fillId);
      const text = document.getElementById(textId);

      let strength = 0;
      if (pwd.length >= 8) strength++;
      if (/[a-z]/.test(pwd)) strength++;
      if (/[A-Z]/.test(pwd)) strength++;
      if (/\d/.test(pwd)) strength++;
      if (/[\W_]/.test(pwd)) strength++;

      const width = strength * 20;
      let color = '#ccc', label = 'Too Weak';

      if (strength === 1) { color = '#e74c3c'; label = 'Weak'; }
      else if (strength === 2) { color = '#e67e22'; label = 'Fair'; }
      else if (strength === 3) { color = '#f1c40f'; label = 'Good'; }
      else if (strength >= 4) { color = '#2ecc71'; label = 'Strong'; }

      fill.style.width = width + '%';
      fill.style.backgroundColor = color;
      text.textContent = label;

      checkMatch();
    }

    function checkMatch() {
      const pwd = document.getElementById('new_password').value;
      const confirm = document.getElementById('confirm_password').value;
      const matchBox = document.getElementById('matchStatus');

      if (confirm.length === 0) {
        matchBox.textContent = '';
        return;
      }

      if (pwd === confirm) {
        matchBox.textContent = '✅ Passwords match';
        matchBox.style.color = '#2ecc71';
      } else {
        matchBox.textContent = '❌ Passwords do not match';
        matchBox.style.color = '#e74c3c';
      }
    }
  </script>
  <?php include '../menu_icon.php'; ?>
<?php include '../footer.php'; ?>
</body>
</html>

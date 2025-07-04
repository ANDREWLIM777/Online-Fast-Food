<?php
require 'db_connect.php';

$showError = false;

// Preserve input values
$fullname = '';
$email = '';
$phone = '';
$password = '';
$confirmPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $allowed_domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com'];
    $email_domain = strtolower(substr(strrchr($email, "@"), 1));

    if (empty($fullname) || empty($email) || empty($password) || empty($confirmPassword) || empty($phone)) {
        $showError = "❌ All fields are required.";
    } elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
        $showError = "⚠️ Phone number must be exactly 11 digits.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $showError = "⚠️ Please enter a valid email address.";
    } elseif (!in_array($email_domain, $allowed_domains)) {
        $showError = "⚠️ Only emails from " . implode(", ", $allowed_domains) . " are allowed.";
    } elseif (strlen($password) < 8) {
        $showError = "⚠️ Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $showError = "⚠️ Password must include at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $showError = "⚠️ Password must include at least one lowercase letter.";
    } elseif (!preg_match('/[\W_]/', $password)) {
        $showError = "⚠️ Password must include at least one special character.";
    } elseif ($password !== $confirmPassword) {
        $showError = "❌ Password and Confirm Password do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Check for duplicate email or phone
$check = $conn->prepare("SELECT id FROM customers WHERE email = ? OR phone = ?");
        if ($check) {
    $check->bind_param("ss", $email, $phone);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // Fetch to identify if it's email or phone causing conflict
        $check->bind_result($existingId);
        $check->fetch();

        // Optional: Do more advanced check here by selecting full row and comparing
        $duplicateStmt = $conn->prepare("SELECT email, phone FROM customers WHERE email = ? OR phone = ?");
        $duplicateStmt->bind_param("ss", $email, $phone);
        $duplicateStmt->execute();
        $duplicateResult = $duplicateStmt->get_result();
        $row = $duplicateResult->fetch_assoc();
        if ($row) {
            if ($row['email'] === $email) {
                $showError = "⚠️ This email is already registered. Try logging in instead.";
            } elseif ($row['phone'] === $phone) {
                $showError = "⚠️ This phone number is already in use. Please use another.";
            } else {
                $showError = "⚠️ Duplicate registration information.";
            }
        } else {
            $showError = "⚠️ This email or phone number is already in use.";
        }
    } else {
        // Proceed with registration
        $stmt = $conn->prepare("INSERT INTO customers (fullname, email, password, phone) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $fullname, $email, $hashedPassword, $phone);
            if ($stmt->execute()) {
                header("Location: register-success.php");
                exit();
            } else {
                $showError = "❌ Something went wrong. Please try again.";
            }
        }
    }
        } else {
            $showError = "❌ Database error (prepare failed).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Brizo Fast Food Melaka - Register</title>
  <link rel="stylesheet" href="register.css" />
  <style>
    .strength-bar {
      height: 8px;
      border-radius: 5px;
      background: #ccc;
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
    }
    .error-box {
      background: #ffe6e6;
      padding: 12px;
      color: #b00020;
      margin-bottom: 16px;
      border-radius: 8px;
    }
    .password-wrapper {
      display: flex;
      align-items: center;
    }
    .password-wrapper input {
      flex: 1;
    }
    .eye-icon {
      cursor: pointer;
      margin-left: 8px;
      user-select: none;
    }
    #passwordMatchMessage {
      font-size: 0.85rem;
      margin-top: 4px;
      font-weight: bold;
    }
  </style>
</head>
<body>
<div class="register-container">
  <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
  <div class="alert-success">✅ Your account has been successfully deleted.</div>
  <?php endif; ?>

  <div class="logo">
    <img src="logo.png" alt="Brizo Fast Food Melaka Logo" width="160" />
  </div>

  <h2>Customer Registration</h2>

  <?php if ($showError): ?>
    <div class="error-box"><?= htmlspecialchars($showError) ?></div>
  <?php endif; ?>

  <form action="register.php" method="POST" novalidate>
    <label for="fullname">Full Name</label>
    <input type="text" id="fullname" name="fullname" required value="<?= htmlspecialchars($fullname) ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" required oninput="validateEmail()" value="<?= htmlspecialchars($email) ?>">
    <div id="emailError" class="strength-text"></div>

    <label for="password">Password</label>
    <div class="password-wrapper">
      <input type="password" id="registerPassword" name="password" required oninput="updateStrength(); checkPasswordMatch();" value="<?= htmlspecialchars($password) ?>">
      <span class="eye-icon" onclick="togglePassword('registerPassword', this)">👁️</span>
    </div>
    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
    <div class="strength-text" id="strengthText"></div>
    <div class="strength-text" id="passwordValidation"></div>


    <label for="confirm_password">Confirm Password</label>
    <div class="password-wrapper">
      <input type="password" id="confirmPassword" name="confirm_password" required oninput="checkPasswordMatch()" value="<?= htmlspecialchars($confirmPassword) ?>">
      <span class="eye-icon" onclick="togglePassword('confirmPassword', this)">👁️</span>
    </div>
    <div id="passwordMatchMessage"></div>

    <label for="phone">Phone Number</label>
    <input type="tel" id="phone" name="phone" required pattern="[0-9]{11}" value="<?= htmlspecialchars($phone) ?>">

    <button type="submit">🍟 Register Now</button>
  </form>

  <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
<script>
function togglePassword(fieldId, iconElement) {
  const field = document.getElementById(fieldId);
  if (field.type === "password") {
    field.type = "text";
    iconElement.textContent = "🙈";
  } else {
    field.type = "password";
    iconElement.textContent = "👁️";
  }
}

function updateStrength() {
  const pwd = document.getElementById('registerPassword').value;
  const fill = document.getElementById('strengthFill');
  const text = document.getElementById('strengthText');
  const validationMsg = document.getElementById('passwordValidation');

  let strength = 0;
  const messages = [];

  if (pwd.length >= 8) {
    strength++;
  } else {
    messages.push("❌ At least 8 characters");
  }

  if (/[a-z]/.test(pwd)) {
    strength++;
  } else {
    messages.push("❌ Add a lowercase letter");
  }

  if (/[A-Z]/.test(pwd)) {
    strength++;
  } else {
    messages.push("❌ Add an uppercase letter");
  }

  if (/\d/.test(pwd)) {
    strength++;
  } else {
    messages.push("❌ Add a number");
  }

  if (/[^a-zA-Z0-9]/.test(pwd)) {
    strength++;
  } else {
    messages.push("❌ Add a special character");
  }

  const width = strength * 20;
  let color = '#ccc', label = 'Too Weak';

  if (strength === 1) { color = '#e74c3c'; label = 'Weak'; }
  else if (strength === 2) { color = '#e67e22'; label = 'Fair'; }
  else if (strength === 3) { color = '#f1c40f'; label = 'Good'; }
  else if (strength === 4) { color = '#2ecc71'; label = 'Strong'; }
  else if (strength >= 5) { color = '#2ecc71'; label = 'Perfect'; }

  fill.style.width = width + '%';
  fill.style.backgroundColor = color;
  text.textContent = label;

  if (pwd === "") {
    validationMsg.textContent = "";
  } else if (messages.length > 0) {
    validationMsg.style.color = "#e67e22";
    validationMsg.innerHTML = messages.join("<br>");
  } else {
    validationMsg.style.color = "#2ecc71";
    validationMsg.textContent = "✅ Password is strong and valid.";
  }
}

function checkPasswordMatch() {
  const password = document.getElementById("registerPassword").value;
  const confirmPassword = document.getElementById("confirmPassword").value;
  const message = document.getElementById("passwordMatchMessage");

  if (!confirmPassword) {
    message.textContent = '';
    return;
  }

  if (password === confirmPassword) {
    message.textContent = "✅ Passwords match";
    message.style.color = "#2ecc71";
  } else {
    message.textContent = "❌ Passwords do not match";
    message.style.color = "#e74c3c";
  }
}

['registerPassword', 'confirmPassword'].forEach(id => {
  document.getElementById(id).addEventListener('keydown', function(e) {
    if (e.key === " ") e.preventDefault();
  });
});

const phoneInput = document.getElementById('phone');
phoneInput.addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '');
});

function validateEmail() {
  const emailInput = document.getElementById("email").value.trim();
  const emailError = document.getElementById("emailError");

  const allowedDomains = ["gmail.com", "yahoo.com", "outlook.com", "hotmail.com"];
  const emailPattern = /^[a-zA-Z0-9._%+-]+@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})$/;

  if (!emailPattern.test(emailInput)) {
    emailError.textContent = "❌ Invalid email format.";
    emailError.style.color = "#e74c3c";
    return;
  }

  const domain = emailInput.split("@")[1].toLowerCase();

  if (!allowedDomains.includes(domain)) {
    emailError.textContent = "⚠️ Only " + allowedDomains.join(", ") + " are allowed.";
    emailError.style.color = "#e67e22";
  } else {
    emailError.textContent = "✅ Email is valid.";
    emailError.style.color = "#2ecc71";
  }
}

// Run validations if data is prefilled
window.addEventListener('DOMContentLoaded', () => {
  updateStrength();
  checkPasswordMatch();
  validateEmail();
});
</script>

</body>
</html>

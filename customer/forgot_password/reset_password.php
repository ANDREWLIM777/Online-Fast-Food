<?php
require_once("../db_connect.php");
session_start();

// Initialize variables
$error = '';
$success = '';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate session email
if (!isset($_SESSION['reset_email']) || !filter_var($_SESSION['reset_email'], FILTER_VALIDATE_EMAIL)) {
    header("Location: ../forgot_password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strpos($password, ' ') !== false) {
            $error = "Password cannot contain spaces.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/", $password)) {
            $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $email = $_SESSION['reset_email'];

            // Check if new password is same as old
            $check_query = "SELECT password FROM customers WHERE email = ?";
            $stmt_check = $conn->prepare($check_query);
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->bind_result($existing_hashed_password);
            $stmt_check->fetch();
            $stmt_check->close();

            if (password_verify($password, $existing_hashed_password)) {
                $error = "New password cannot be the same as the old password.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE customers SET password = ? WHERE email = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $hashed_password, $email);
                if ($stmt->execute()) {
                    $success = "Password reset successfully. You can now <a href='/Online-Fast-Food/customer/login.php'>log in</a>.";
                    unset($_SESSION['reset_email'], $_SESSION['csrf_token']);
                } else {
                    $error = "Failed to reset password: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Brizo Fast Food</title>
    <link href="https://fonts.googleapis.com/css?family=Fredoka:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Fredoka', sans-serif;
            background: linear-gradient(to right, #ffe259, #ffa751);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 400px;
        }
        .form_btn {
            background: linear-gradient(to right, #ffa751, #ffe259);
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            color: white;
            font-weight: bold;
        }
        .form-control {
            border-radius: 30px;
            border: 2px solid #ffe259;
        }
        .error { color: red; font-size: 0.9em; }
        .success { color: green; font-size: 0.9em; }
        .strength-label {
            font-size: 0.9em;
            margin-top: 5px;
            margin-bottom: 3px;
        }
        #strengthBar {
            height: 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
<div class="login-box text-center">
    <img src="/Online-Fast-Food/customer/logo.png" alt="Brizo Fast Food" class="img-fluid logo mb-3">
    <h4 class="mb-4"><i class="fas fa-lock"></i> Reset Your Password</h4>

    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php else: ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-group text-left">
            <label for="password">New Password</label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" required>
                <div class="input-group-append">
                    <button class="btn btn-light toggle-password" type="button" data-target="password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="strength-label" id="strengthText"></div>
            <div id="strengthBar" class="w-100 bg-light">
                <div id="strengthFill" class="bg-danger" style="width: 0%; height: 100%;"></div>
            </div>
        </div>

        <div class="form-group text-left">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-group">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                <div class="input-group-append">
                    <button class="btn btn-light toggle-password" type="button" data-target="confirm_password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <small id="matchMessage"></small>
        </div>

        <button type="submit" class="btn form_btn btn-block">Reset Password</button>
    </form>
    <?php endif; ?>
    <hr>
    <p><a href="/Online-Fast-Food/customer/login.php" style="color: #ffa751;">Back to Login</a></p>
</div>

<script>
    document.getElementById("password").addEventListener("input", function () {
        const password = this.value;
        const strengthText = document.getElementById("strengthText");
        const strengthFill = document.getElementById("strengthFill");

        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        const labels = ["Very Weak", "Weak", "Moderate", "Strong", "Very Strong"];
        const colors = ["bg-danger", "bg-warning", "bg-info", "bg-primary", "bg-success"];

        strengthText.textContent = labels[strength - 1] || "";
        strengthFill.style.width = (strength * 20) + "%";
        strengthFill.className = colors[strength - 1] || "";

        checkMatch();
    });

    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            const icon = this.querySelector('i');
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    });

    document.querySelectorAll('input[type="password"]').forEach(input => {
        input.addEventListener('keydown', function (e) {
            if (e.key === " ") e.preventDefault();
        });
    });

    const passwordInput = document.getElementById("password");
    const confirmInput = document.getElementById("confirm_password");
    const matchMessage = document.getElementById("matchMessage");

    function checkMatch() {
        if (confirmInput.value === "") {
            matchMessage.textContent = "";
            return;
        }
        if (passwordInput.value === confirmInput.value) {
            matchMessage.textContent = "Passwords match";
            matchMessage.className = "text-success";
        } else {
            matchMessage.textContent = "Passwords do not match";
            matchMessage.className = "text-danger";
        }
    }

    confirmInput.addEventListener("input", checkMatch);
</script>
</body>
</html>

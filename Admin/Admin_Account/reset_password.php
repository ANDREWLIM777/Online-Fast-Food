<?php
require_once '../Admin_Account/db.php';
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
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token. Please try again.";
    } else {
        $password = $_POST['new_password'] ?? '';
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
            $check_query = "SELECT password FROM admin WHERE email = ?";
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
                $query = "UPDATE admin SET password = ? WHERE email = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $hashed_password, $email);
                if ($stmt->execute()) {
                    $success = "Password reset successfully. You can now <a href='login.php'>log in</a>.";
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
    <meta Dummy Data name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Brizo Fast Food Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Fredoka', sans-serif;
            background: #0c0a10;
            color: #eee;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .container {
            max-width: 500px;
            margin: auto;
            background: #1a1a1a;
            padding: 30px;
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
        .form-control {
            width: 100%;
            padding: 12px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            color: #fff;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #c0a23d;
            color: #000;
            font-weight: bold;
            border-radius: 8px;
            border: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        .error, .success {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .error {
            background: #4e1e1e;
            color: #f88;
        }
        .success {
            background: #1e4e28;
            color: #9f9;
        }
        .back-btn {
            margin-top: 15px;
            display: inline-block;
            color: #c0a23d;
            text-decoration: none;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .strength-label {
            font-size: 0.9em;
            margin-top: 5px;
            margin-bottom: 3px;
        }
        #strengthBar {
            height: 8px;
            border-radius: 10px;
            background: #444;
        }
        .input-group-append .btn-light {
            background: #2a2a2a;
            border: 1px solid #444;
            color: #c0a23d;
        }
    </style>
</head>
<body>
    <div class="container text-center">
        <img src="/Online-Fast-Food/customer/logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h2>Reset Password</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="new_password" class="form-control" required>
                        <div class="input-group-append">
                            <button class="btn btn-light toggle-password" type="button" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="strength-label" id="strengthText"></div>
                    <div id="strengthBar" class="w-100">
                        <div id="strengthFill" style="width: 0%; height: 100%;"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id "confirm_password" class="form-control" required>
                        <div class="input-group-append">
                            <button class="btn btn-light toggle-password" type="button" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <small id="matchMessage"></small>
                </div>
                <button type="submit" class="btn">Save Password</button>
            </form>
        <?php endif; ?>
        <a href="login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login Page</a>
    </div>
    <script>
        document.getElementById("new_password").addEventListener("input", function () {
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
            const colors = ["#dc3545", "#ffc107", "#17a2b8", "#007bff", "#28a745"];

            strengthText.textContent = labels[strength - 1] || "";
            strengthFill.style.width = (strength * 20) + "%";
            strengthFill.style.background = colors[strength - 1] || "";

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

        const passwordInput = document.getElementById("new_password");
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
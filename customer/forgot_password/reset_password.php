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
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Password validation
        if (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/", $password)) {
            $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } else {
            $email = $_SESSION['reset_email'];
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password in database
            $query = "UPDATE customers SET password = ? WHERE email = ?";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                $error = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("ss", $hashed_password, $email);
                if ($stmt->execute()) {
                    $success = "Password reset successfully. You can now <a href='/Online-Fast-Food/customer/login.php'>log in</a>.";
                    // Clear session variables
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['csrf_token']);
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
            animation: fadeIn 0.8s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form_btn {
            background: linear-gradient(to right, #ffa751, #ffe259);
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            color: white;
            font-weight: bold;
            transition: 0.3s ease;
        }
        .form_btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        .form-control {
            border-radius: 30px;
            border: 2px solid #ffe259;
        }
        .error { color: red; font-size: 0.9em; }
        .success { color: green; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="login-box text-center">
        <img src="/Online-Fast-Food/assets/images/brizo-logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h4 class="mb-4"><i class="fas fa-lock"></i> Reset Your Password</h4>
        <?php if ($error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        <?php if ($success) { ?>
            <p class="success"><?php echo $success; ?></p>
        <?php } else { ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
            </div>
            <button type="submit" class="btn form_btn btn-block">Reset Password</button>
        </form>
        <?php } ?>
        <hr>
        <p><a href="/Online-Fast-Food/customer/login.php" style="color: #ffa751;">Back to Login</a></p>
    </div>
</body>
</html>
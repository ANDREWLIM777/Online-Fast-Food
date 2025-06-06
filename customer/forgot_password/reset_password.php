<?php
require_once('../db_connect.php');
session_start();
if (isset($_SESSION["login_sess"])) {
    header("Location: account.php");
    exit;
}
$email = $_GET['email'] ?? '';
if (empty($email)) {
    header("Location: forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Brizo Fast Food</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500&display=swap" rel="stylesheet">
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
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .toast {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-box text-center">
        <img src="assets/images/brizo-logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h4 class="mb-4"><i class="fas fa-lock"></i> Reset Password</h4>
        <form id="resetForm">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required minlength="8">
            </div>
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn form_btn btn-block">Reset Password</button>
        </form>
        <p class="mt-3"><a href="login.php" style="color: #ffa751;">Back to Login</a></p>
    </div>
    <div class="toast-container" id="toastContainer"></div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#resetForm').on('submit', function(e) {
                e.preventDefault();
                const password = $('input[name=password]').val();
                const confirm = $('input[name=confirm_password]').val();
                if (password.length < 8) {
                    showToast("Password must be at least 8 characters.", "error");
                    return;
                }
                if (password !== confirm) {
                    showToast("Passwords do not match.", "error");
                    return;
                }
                const $btn = $('button[type="submit"]');
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Resetting...');
                $.ajax({
                    url: 'reset_process.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        try {
                            const res = JSON.parse(response);
                            showToast(res.message, res.status);
                            if (res.status === 'success') {
                                setTimeout(() => {
                                    window.location.href = 'login.php';
                                }, 2000);
                            }
                        } catch (e) {
                            showToast("Invalid server response.", "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast("Server error. Try again later.", "error");
                        console.error("AJAX error:", status, error);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('Reset Password');
                    }
                });
            });
            function showToast(message, type = 'info') {
                const toast = `
                    <div class="toast show bg-${type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'secondary')} text-white mb-2" role="alert">
                        <div class="toast-body"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${message}</div>
                    </div>`;
                $('#toastContainer').append(toast);
                setTimeout(() => $('.toast').fadeOut().remove(), 3000);
            }
        });
    </script>
</body>
</html>
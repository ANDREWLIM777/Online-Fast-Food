<?php
require_once("../db_connect.php");
session_start();

// Redirect logged-in users to account page
if (isset($_SESSION["login_sess"])) {
    header("Location: account.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Brizo Fast Food</title>
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
        <img src="/Online-Fast-Food/customer/logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h4 class="mb-4"><i class="fas fa-lock"></i> Forgot Your Password?</h4>
        <form id="forgotForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="btn form_btn btn-block">Send OTP</button>
        </form>
        <hr>
        <p>Remembered? <a href="/Online-Fast-Food/customer/login.php" style="color: #ffa751;">Login</a></p>
        <p>No account? <a href="/Online-Fast-Food/customer/register.php" style="color: #ffa751;">Sign up</a></p>
    </div>
    <div class="toast-container" id="toastContainer"></div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#forgotForm').on('submit', function(e) {
                e.preventDefault();
                const email = $('input[name=email]').val().trim();
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showToast("Please enter a valid email.", "error");
                    return;
                }
                const $btn = $('button[type="submit"]');
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
                $.ajax({
                    url: 'forgot_process.php',
                    type: 'POST',
                    data: { email: email },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Response:', response);
                        showToast(response.message, response.status);
                        if (response.status === 'success') {
                            setTimeout(() => {
                                window.location.href = 'verify_otp.php';
                            }, 2000);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error, 'Response:', xhr.responseText);
                        let errorMessage = "Server error: Unable to process request.";
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            errorMessage += " (Invalid response format)";
                        }
                        showToast(errorMessage, "error");
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('Send OTP');
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
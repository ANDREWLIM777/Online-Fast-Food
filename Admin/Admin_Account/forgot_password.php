<?php
require_once '../Admin_Account/db.php';
session_start();

if (isset($_SESSION['login_sess'])) {
    header("Location: account.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta Dummy Data name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Brizo Fast Food Admin</title>
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
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .toast {
            border-radius: 10px;
            box-shadow: 2px 2px 10px rgba(0,0,0,0.2);
        }
        .error {
            background: #4e1e1e;
            color: #f88;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
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
    </style>
</head>
<body>
    <div class="container text-center">
        <img src="/Online-Fast-Food/customer/logo.png" alt="Brizo Fast Food" class="img-fluid logo">
        <h2>Forgot Password</h2>
        <form id="forgotForm">
            <div class="form-group">
                <label for="email">Enter your email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="btn">Send OTP</button>
        </form>
        <a href="login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login Page</a>
        <div class="toast-container" id="toastContainer"></div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#forgotForm').on('submit', function(e) {
                e.preventDefault();
                const email = $('input[name=email]').val();
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showToast("Please enter a valid email.", "error");
                    return;
                }
                const $btn = $('button[type="submit"]');
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
                $.ajax({
                    url: 'forgot_process.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        showToast(response.message, response.status);
                        if (response.status === 'success') {
                            setTimeout(() => {
                                window.location.href = 'otp_verify.php?email=' + encodeURIComponent(email);
                            }, 2000);
                        }
                    },
                    error: function(xhr) {
                        showToast("Server error: " + xhr.responseText, "error");
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('Send OTP');
                    }
                });
            });
            function showToast(message, type = 'info') {
                const toast = `
                    <div class="toast show bg-${type === 'success' ? 'success' : 'danger'} text-white mb-2" role="alert">
                        <div class="toast-body"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${message}</div>
                    </div>`;
                $('#toastContainer').append(toast);
                setTimeout(() => $('.toast').fadeOut().remove(), 3000);
            }
        });
    </script>
</body>
</html>
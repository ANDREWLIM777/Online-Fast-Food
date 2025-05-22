<?php
require '../db_connect.php'; // Adjust path based on your project structure

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 400);
    }

    $login = trim($_POST['login_var'] ?? '');

    if (empty($login)) {
        throw new Exception("Please provide a username or email.", 400);
    }

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error);
        throw new Exception("Database query preparation failed: " . $conn->error, 500);
    }
    $stmt->bind_param("ss", $login, $login);
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        throw new Exception("Database query execution failed: " . $stmt->error, 500);
    }
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("No account found for the provided username or email.", 404);
    }

    $row = $result->fetch_assoc();
    $oldftemail = $row['email'];

    // Generate token
    $token = bin2hex(random_bytes(50));

    // Store token in pass_reset table
    $stmt = $conn->prepare("INSERT INTO pass_reset (email, token) VALUES (?, ?)");
    if (!$stmt) {
        error_log("Insert preparation failed: " . $conn->error);
        throw new Exception("Database insert preparation failed: " . $conn->error, 500);
    }
    $stmt->bind_param("ss", $oldftemail, $token);
    if (!$stmt->execute()) {
        error_log("Insert execution failed: " . $stmt->error);
        throw new Exception("Failed to store reset token: " . $stmt->error, 500);
    }

    // Email configuration
    $fromName = "Brizo Fast Food Melaka";
    $fromEmail = "no-reply@brizofastfood.com";
    $replyTo = "support@brizofastfood.com";
    $credits = "All rights are reserved | Brizo Fast Food Melaka";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
    $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $replyTo . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $subject = "Brizo Fast Food - Password Reset Link";
    $msg = "
        <html>
        <head>
            <style>
                body { font-family: 'Fredoka', sans-serif; }
                h2 { color: #ffa751; }
                strong { font-size: 1.2em; }
                p.credit { color: #ffe259; }
            </style>
        </head>
        <body>
            <h2>Brizo Fast Food Melaka</h2>
            <p>Your password reset link: <br><a href='http://localhost/Online-Fast-Food/customer/forgot_password/password-reset.php?token=" . $token . "'>Click here to reset your password</a></p>
            <p>This link is valid for a limited time. If you didn’t request this, please ignore this email.</p>
            <p class='credit'><center>$credits</center></p>
        </body>
        </html>
    ";
    $altMsg = "Your password reset link: http://localhost/Online-Fast-Food/customer/forgot_password/password-reset.php?token=" . $token . "\nValid for a limited time.\nIf you didn’t request this, ignore this email.\n$credits";

    // Send email using mail()
    error_clear_last();
    $mailResult = @mail($oldftemail, $subject, $msg, $headers);
    if ($mailResult) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Password reset link sent to your email! Check your inbox or spam folder.'
        ]);
    } else {
        $error = error_get_last();
        $errorMessage = $error ? $error['message'] : 'Unknown mail error';
        error_log("Mail function failed for email: $oldftemail. Error: $errorMessage. Headers: $headers");
        throw new Exception("Failed to send reset link: $errorMessage", 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
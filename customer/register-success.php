<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registration Successful!</title>
  <link rel="stylesheet" href="register-success.css">
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500&display=swap" rel="stylesheet">
  <meta http-equiv="refresh" content="10;url=login.php"> <!-- auto-redirect -->
</head>
<body>
  <div class="success-container">
    <div class="logo">
      <img src="logo.png" alt="Brizo Fast Food Logo" />
    </div>
    <div class="checkmark">✔️</div>
    <h1>You're In, Brizo Champ!</h1>
    <p>Welcome to Brizo Fast Food Melaka 🍔<br>Get ready to feast.</p>
    <a href="login.php" class="login-btn">🍟 Proceed to Login</a>
  </div>

  <!-- Optional Confetti Animation -->
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
  <script>
    confetti({
      particleCount: 150,
      spread: 70,
      origin: { y: 0.6 }
    });
  </script>
</body>
</html>

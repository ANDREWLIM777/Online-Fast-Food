<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Brizo Fast Food Melaka 🍔</title>
  <link rel="stylesheet" href="home.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<header class="navbar">
<img src="/Online-Fast-Food/customer/menu/pictures/burger-bg1.png" alt="Brizo Fast Food Logo" class="brizo-logo">

  <nav>
    <a href="/Online-Fast-Food/customer/menu/menu.php">Menu</a>
    <a href="/Online-Fast-Food/customer/login.php">My Orders</a>
    <a href="/Online-Fast-Food/customer/menu/cart/cart.php">Cart</a>
    <?php if (isset($_SESSION['customer_id'])): ?>
      <a href="../login.php" class="btn-logout">Logout</a>
    <?php else: ?>
      <a href="../login.php" class="btn-login">Login</a>
    <?php endif; ?>
  </nav>
</header>

<section class="hero">
  <div class="hero-content">
    <h1>Brizo Fast Food Melaka</h1>
    <p class="sub">Where hunger meets satisfaction. Local flavour, global taste.</p>
    <a href="/Online-Fast-Food/customer/menu/menu.php" class="cta-button">Explore the Menu</a>
  </div>
</section>

<section class="features">
  <div class="feature">
    <h2>🍔 Premium Quality</h2>
    <p>Only the best ingredients — hand-picked and chef-approv  ed.</p>
  </div>
  <div class="feature">
    <h2>⚡ Ultra Fast Delivery</h2>
    <p>Lightning-fast service. Delivered hot to your doorstep.</p>
  </div>
  <div class="feature">
    <h2>🔐 Trusted & Secure</h2>
    <p>Protected transactions with multiple secure payment gateways.</p>
  </div>
</section>  

<script src="home.js"></script>
</body>
</html>

<?php include '../footer.php'; ?>
<?php include '../footer2.php'; ?>
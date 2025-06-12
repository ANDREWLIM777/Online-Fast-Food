<?php
require 'db_connect.php';
session_start();

// 🛒 Get cart count (only for logged-in customers, not guest)
$cartCount = 0;
$isGuest = isset($_SESSION['is_guest']) ? (bool)$_SESSION['is_guest'] : false;
if (isset($_SESSION['customer_id']) && !$isGuest) {
    $customerId = $_SESSION['customer_id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total_items FROM cart WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($cartCount);
    $stmt->fetch();
    $stmt->close();
}

// 🔔 Get unread customer notifications count
$unreadNotif = 0;
if (isset($_SESSION['customer_id']) && !$isGuest) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['customer_id']);
    $stmt->execute();
    $stmt->bind_result($unreadNotif);
    $stmt->fetch();
    $stmt->close();
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$params = [];
$sql = "SELECT id, category, item_name, description, price, photo FROM menu_items WHERE is_available = 1";

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
}
if (!empty($search)) {
    $sql .= " AND MATCH(item_name, description) AGAINST (?)";
    $params[] = $search;
}
$sql .= " ORDER BY category, item_name";

$stmt = $conn->prepare($sql);
if ($params) {
    $types = str_repeat("s", count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$menuByCategory = [];
while ($row = $result->fetch_assoc()) {
    $menuByCategory[$row['category']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Brizo Menu</title>
  <link rel="stylesheet" href="menu.css">
</head>
<body>

<!-- 🔥 Hero Banner -->
<div class="hero-slide">
  <div>Welcome to Brizo Fast Food Melaka</div>
</div>

<!-- my orders-floating -->
<a href="/Online-Fast-Food/customer/orders/orders.php" class="notification-bell">
  <svg xmlns="http://www.w3.org/2000/svg" class="orders-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 8h14l-1.5 12h-11L5 8zm2 0V6a5 5 0 0110 0v2" />
  </svg>
</a>


<!-- 🛒 Floating Cart Icon (for logged-in users) -->
<?php if (!$isGuest): ?>
  <a href="cart/cart.php" class="cart-floating-btn" id="cart-icon">
    🛒
    <span id="cart-count" class="cart-count"><?= (int)$cartCount ?></span>
  </a>
<?php endif; ?>

<!--  Floating Log In / Sign Up Button (for guests) -->
<?php if ($isGuest): ?>
  <a href="/Online-Fast-Food/customer/login.php" class="login-floating-btn" id="login-icon">Sign Up / Log In</a>
<?php endif; ?>

<!-- 🔍 Filter Form -->
<form method="GET" class="filter-form">
  <input type="text" name="search" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
  
  <select name="category" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <option value="burger" <?= $category == 'burger' ? 'selected' : '' ?>>Burger</option>
    <option value="chicken" <?= $category == 'chicken' ? 'selected' : '' ?>>Chicken</option>
    <option value="drink" <?= $category == 'drink' ? 'selected' : '' ?>>Drink</option>
    <option value="snacks" <?= $category == 'snacks' ? 'selected' : '' ?>>Snacks</option>
    <option value="meal" <?= $category == 'meal' ? 'selected' : '' ?>>Meal</option>
  </select>

  <button type="submit">Search</button>
</form>

<?php if (!empty($_SESSION['guest_notice'])): ?>
  <div class="guest-toast"><?= htmlspecialchars($_SESSION['guest_notice']) ?></div>
  <?php unset($_SESSION['guest_notice']); ?>
<?php endif; ?>

<!-- 🍔 Menu Items -->
<?php if (empty($menuByCategory)): ?>
  <p>No menu items found.</p>
<?php else: ?>
  <?php foreach ($menuByCategory as $category => $items): ?>
    <section class="menu-category">
      <h2><?= ucfirst($category) ?></h2>
      <div class="menu-grid-square">
        <?php foreach ($items as $item): ?>
          <div class="menu-card-square">
            <?php if (!empty($item['photo'])): ?>
              <img src="/ONLINE-FAST-FOOD/Admin/Manage_Menu_Item/<?= htmlspecialchars($item['photo']) ?>" 
                   alt="<?= htmlspecialchars($item['item_name']) ?>" 
                   class="menu-img-square product-img">
            <?php endif; ?>

            <div class="menu-info">
              <h3><?= htmlspecialchars($item['item_name']) ?></h3>
              <p class="price">RM <?= number_format($item['price'], 2) ?></p>

              <?php if ($isGuest): ?>
                <a href="/Online-Fast-Food/customer/login.php" class="sign-in-link">Sign in to start ordering!</a>
              <?php else: ?>
                <!-- 🛒 Normal Customer Form -->
                <form class="add-to-cart-form" data-id="<?= $item['id'] ?>">
                  <button type="submit" class="add-to-cart-btn">🛒 Add to Cart</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<!-- JS and Footer -->
<script src="menu.js"></script>
<?php include '../menu_icon.php'; ?>
<?php include '../footer.php'; ?>
<?php include '../footer2.php'; ?>
</body>
</html>

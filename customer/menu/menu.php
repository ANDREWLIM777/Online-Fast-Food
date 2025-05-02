<?php
require 'db_connect.php';
session_start();

// 🛒 Get cart count (only for logged-in customers, not guest)
$cartCount = 0;
if (isset($_SESSION['customer_id']) && empty($_SESSION['is_guest'])) {
    $customerId = $_SESSION['customer_id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total_items FROM cart WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $stmt->bind_result($cartCount);
    $stmt->fetch();
    $stmt->close();
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$params = [];
$sql = "SELECT id, category, item_name, description, price, promotion, photo FROM menu_items WHERE is_available = 1";

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

<!-- 🛒 Floating Cart Icon -->
<a href="cart/cart.php" class="cart-floating-btn" id="cart-icon">
  🛒
  <span id="cart-count" class="cart-count"><?= (int)$cartCount ?></span>
</a>

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

              <?php if (!empty($item['promotion'])): ?>
                <p class="promo-tag">🔥 <?= htmlspecialchars($item['promotion']) ?></p>
              <?php endif; ?>

              <?php if (!empty($_SESSION['is_guest'])): ?>
                <!-- 👤 Guest still needs a form to detect blocking -->
                <form class="add-to-cart-form guest-block" data-id="<?= $item['id'] ?>">
                  <button type="submit" class="add-to-cart-btn">🛒 Add to Cart (Guest)</button>
                </form>
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

<!--  JS and Footer -->
<script src="menu.js"></script>
<?php include '../menu_icon.php'; ?>
<?php include '../footer.php'; ?>
<?php include '../footer2.php'; ?>
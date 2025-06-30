<?php
require '../db_connect.php'; // Connect to your database
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>About Brizo Fast Food</title>
  <link rel="stylesheet" href="about_brizo.css">
  <link rel="stylesheet" href="../menu/order_now_button/order_now.css">
  <style>
    .admin-section {
      margin-top: 50px;
    }
    .admin-card {
      display: flex;
      align-items: center;
      border: 1px solid #ccc;
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 20px;
      background: #f9f9f9;
    }
    .admin-card img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 50%;
      margin-right: 20px;
      border: 2px solid #ffa751;
    }
    .admin-info h4 {
      margin: 0 0 5px;
      font-size: 18px;
    }
    .admin-info p {
      margin: 3px 0;
      font-size: 14px;
      color: #333;
    }
    .admin-role {
      font-weight: bold;
      color: #ff4757;
    }
  </style>
</head>
<body>

<div class="top-right-button">
  <a href="../menu/menu.php" class="button-cool-effect">🍔 Order Now</a>
</div>

<div class="about-wrapper">
  <div class="logo-container">
    <img src="/Online-Fast-Food/customer/menu/pictures/burger-bg1.png" alt="Brizo Fast Food Logo" class="brizo-logo">
  </div>

  <h1>About Brizo Fast Food Melaka</h1>

  <p>The Brizo story began with the humble opening of our very first restaurant in the heart of Melaka in 2024. With passion for flavor and local culture, we’ve quickly grown into a beloved name known for delivering bold taste, affordable prices, and warm service to everyone who walks through our doors.</p>

  <p>Today, Brizo Fast Food serves customers across the region — offering fresh, high-quality meals crafted with care and inspired by local favorites. Whether you're craving crispy chicken, handcrafted burgers, or refreshing drinks, Brizo is your go-to spot for quick, satisfying meals.</p>

  <p class="tagline">Trusted, Tasty, and Truly Malaysian – That’s Brizo.</p>

  <p>We’re proud to be Halal certified and deeply committed to quality, community, and creating memorable food moments. Our team works every day to make sure you enjoy fast food that’s not just convenient, but “Brizo Good.”</p>

  <!-- ✅ Admin Info Section -->
  <div class="admin-section">
    <h2>Meet Our Admin Team</h2>
    <?php
    $adminQuery = $conn->query("SELECT * FROM admin");
    while ($admin = $adminQuery->fetch_assoc()):
      $photo = !empty($admin['photo']) ? $admin['photo'] : 'default.jpg';
      $photoPath = "/Online-Fast-Food/Admin/Admin_Account/upload/" . htmlspecialchars($photo);
    ?>
    <div class="admin-card">
      <img src="<?= $photoPath ?>" alt="Admin Photo">
      <div class="admin-info">
        <h4><?= htmlspecialchars($admin['name']) ?></h4>
        <p>📞 Phone: <?= htmlspecialchars($admin['phone']) ?></p>
        <p>✉️ Email: <?= htmlspecialchars($admin['email']) ?></p>
        <p class="admin-role">🔐 Role: <?= ucfirst($admin['role']) ?></p>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<?php include '../menu_icon.php'; ?>
<?php include '../footer.php'; ?>
<?php include '../footer2.php'; ?>
</body>
</html>

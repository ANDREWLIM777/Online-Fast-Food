<?php
require 'db_conn.php';
session_start();

function getOrdersByStatus($pdo, $status) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE status = ? ORDER BY created_at ASC");
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

$statuses = ['pending', 'preparing', 'delivering', 'delivered'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">


<style>

.header {
    left: 0;
    right: 0;   
    position: fixed;
    top: 0;
    width: 100%;
    background: 
        linear-gradient(135deg, #000000 0%, #0c0a10 100%),
        repeating-linear-gradient(-30deg, 
            transparent 0px 10px, 
            #f4e3b215 10px 12px,
            transparent 12px 22px);
    padding: 1.8rem 0 ;
    box-shadow: 0 4px 25px rgba(0,0,0,0.06);
    z-index: 999;
    display: flex;
    justify-content: center;
    border-bottom: 1px solid #eee3c975;
    overflow: hidden;
}

.title-group {
    position: relative;
    text-align: center;
    padding: 0 2.5rem;
}

.main-title {
    font-size: 2.1rem;
    background: linear-gradient(45deg, #c0a23d, #907722);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-family: 'Playfair Display', serif;
    letter-spacing: 1.8px;
    line-height: 1.15;
    text-shadow: 1px 1px 2px rgba(255,255,255,0.3);
    margin-bottom: 0.4rem;
    transition: all 0.3s ease;
}

.sub-title {
    font-size: 1.05rem;
    color: #907722;
    font-family: 'Roboto', sans-serif;
    font-weight: 400;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    opacity: 0.9;
    position: relative;
    display: inline-block;
    padding: 0 15px;
}

.sub-title::before,
.sub-title::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 35px;
    height: 1.2px;
    background: linear-gradient(90deg, #c9a227aa, transparent);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.sub-title::before {
    left: -30px;
    transform: translateY(-50%) rotate(-15deg);
}

.sub-title::after {
    right: -30px;
    transform: translateY(-50%) rotate(15deg);
}

.title-group:hover .sub-title::before {
    left: -35px;
    width: 35px;
}

.title-group:hover .sub-title::after {
    right: -35px;
    width: 35px;
}

.header::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 50% 50%, 
        #f4e3b210 0%, 
        transparent 60%);
    animation: auraPulse 8s infinite;
    pointer-events: none;
}

@keyframes auraPulse {
    0% { transform: scale(0.8); opacity: 0.3; }
    50% { transform: scale(1.2); opacity: 0.1; }
    100% { transform: scale(0.8); opacity: 0.3; }
}

.header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 30%, #f4e4b239 1px, transparent 2px),
        radial-gradient(circle at 80% 70%, #f4e4b236 1px, transparent 2px);
    background-size: 40px 40px;
    animation: stardust 20s linear infinite;
}

@keyframes stardust {
    0% { background-position: 0 0, 100px 100px; }
    100% { background-position: 100px 100px, 0 0; }
}

    body {
      font-family: Arial, sans-serif;
      background: #121212;
      color: #eee;
      margin: 0;
      padding: 2rem;
      padding-top: 120px;
      padding-bottom: 50px;
      position: relative;
    }

    /* Background glow effect */
    body::after {
      content: '';
      position: fixed;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 50% 50%, rgba(244, 227, 178, 0.07) 0%, transparent 70%);
      animation: auraPulse 8s infinite;
      pointer-events: none;
      z-index: -1;
    }

    /* Stardust particles */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-image: 
        radial-gradient(circle at 20% 30%, rgba(244, 228, 178, 0.15) 1px, transparent 2px),
        radial-gradient(circle at 80% 70%, rgba(244, 228, 178, 0.15) 1px, transparent 2px);
      background-size: 60px 60px;
      animation: stardust 20s linear infinite;
      pointer-events: none;
      z-index: -2;
    }

    @keyframes auraPulse {
      0% { transform: scale(0.8); opacity: 0.3; }
      50% { transform: scale(1.2); opacity: 0.08; }
      100% { transform: scale(0.8); opacity: 0.3; }
    }

    @keyframes stardust {
      0% { background-position: 0 0, 100px 100px; }
      100% { background-position: 100px 100px, 0 0; }
    }

    h1 {
      text-align: center;
      font-size: 2.5rem;
      color: #c0a23d;
      margin-bottom: 2rem;
    }

    .order-container {
      padding-top: 0px;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 2rem;
    }

    .order-card {
      background: #fffbe6;
      color: #1c1c1c;
      padding: 2rem;
      border-radius: 14px;
      max-width: 800px;
      width: 100%;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      border: 2px solid #c0a23d;
    }

    .order-card h3 {
      margin-top: 0;
      color: #000;
      font-size: 1.5rem;
    }

    .order-card ul {
      list-style: none;
      padding: 0;
      margin: 1rem 0;
    }

    .order-card li {
      margin-bottom: 0.5rem;
    }

    .order-card strong {
      display: block;
      margin-top: 1rem;
      font-size: 1.2rem;
    }

    .actions {
      margin-top: 1rem;
      text-align: center;
    }

    .btn-approve {
      background: #4CAF50;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.2s ease;
    }

    .btn-approve:hover {
      background: #45a049;
    }

     .back-btn {
            display: inline-block;
            position: fixed;
            background: linear-gradient(to right, #c0a23d, #e8d48b);
            color: #000;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: 0.2s ease;
            margin-right: 80rem;
        }

        .back-btn:hover {
            
            background: #e8d48b;
            box-shadow: 0 0 10px #e8d48b;
        }

    @media (max-width: 768px) {
      .order-card {
        max-width: 100%;
      }

      h1 {
        font-size: 2rem;
      }

      .btn-approve {
        padding: 10px 20px;
        font-size: 0.9rem;
      }
    }

.slide-to-confirm {
  position: relative;
  left: 250px;
  width: 300px;
  height: 50px;
  background: linear-gradient(to right, #c0a23d, #e8d48b);
  border-radius: 25px;
  overflow: hidden;
  cursor: pointer;
  user-select: none;
  margin: 0rem auto;
  border: 2px solid #c0a23d;
}

.slider-button {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  width: 130px;
  background: #1a1a1a;
  color: #c0a23d;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 25px;
  font-weight: bold;
  font-family: 'Roboto', sans-serif;
  font-size: 0.95rem;
  z-index: 2;
  transition: background 0.3s ease;
  box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.25);
}

.slide-hint {
  position: absolute;
  width: 140%;
  height: 100%;
  color: rgba(0, 0, 0, 0.25);
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  z-index: 1;
  pointer-events: none;
  letter-spacing: 1px;
}

.tab-container {
  width: 100%;
  position: sticky;
  top: 120px;
  z-index: 10;
  padding: 0.5rem 0;
  background: linear-gradient(to bottom, #1a1a1a 0%, #0d0d0d 100%);
  box-shadow: 0 2px 15px rgba(0,0,0,0.3);
  border-bottom: 1px solid #c0a23d55;
}

.tabs-wrapper {
  position: relative;
  max-width: 900px;
  margin: 0 auto;
  padding: 0 1rem;
}

.tabs {
  display: flex;
  justify-content: space-between;
  position: relative;
  gap: 0.3rem;
}

.tab-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 8px 0;
  background: transparent;
  border: none;
  color: #c0a23d;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  z-index: 1;
  opacity: 0.7;
  min-height: 50px; 
}

.tab-btn i {
  font-size: 1.1rem;
  margin-bottom: 4px;
  text-shadow: 0 0 5px rgba(192, 162, 61, 0.3);
  transition: all 0.3s ease;
}

.tab-content {
  padding-top: 20px;
  display: none;
  animation: fadeIn 0.4s ease-out;
}

.tab-content.active {
  display: block;
}

.tab-btn.active {
  opacity: 1;
  color: #fff;
  text-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
  transform: translateY(-2px);
}

.tab-btn.active i {
  color: #ffd700;
  text-shadow: 0 0 15px rgba(255, 215, 0, 0.9);
  transform: scale(1.1);
}


@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    margin-left: 8px;
    vertical-align: middle;
}

.status-pending {
    background-color: #FFF3CD;
    color: #856404;
    border: 1px solid #FFEEBA;
}

.status-preparing {
    background-color: #D1ECF1;
    color: #0C5460;
    border: 1px solid #BEE5EB;
}

.status-delivering {
    background-color: #D4EDDA;
    color: #155724;
    border: 1px solid #C3E6CB;
}

.status-delivered {
    background-color:rgb(237, 212, 212);
    color:rgb(209, 48, 48);
    border: 1px solid rgb(230, 195, 195);
}

  </style>
</head>
<body>

 <div class="header">

        <div class="title-group">
            <div class="main-title">BRIZO MELAKA</div>
            <div class="sub-title">Order Management Page</div>
        </div>

        <a href="../Main Page/main_page.php" class="back-btn">
        <i class="fas fa-house"></i> Back To Main Page
    </a>

    </div>

<div class="tab-container">
  <div class="tabs-wrapper">
    <div class="tabs">
      <button class="tab-btn active" onclick="showTab(event, 'pending')">
        <i class="fas fa-clock"></i>
        <span>Pending</span>
        <span class="order-count"><?= count(getOrdersByStatus($pdo, 'pending')) ?></span>
      </button>
      <button class="tab-btn" onclick="showTab(event, 'preparing')">
      <i class="fas fa-hamburger"></i>
        <span>Preparing</span>
        <span class="order-count"><?= count(getOrdersByStatus($pdo, 'preparing')) ?></span>
      </button>
      <button class="tab-btn" onclick="showTab(event, 'delivering')">
        <i class="fas fa-motorcycle"></i>
        <span>Delivering</span>
        <span class="order-count"><?= count(getOrdersByStatus($pdo, 'delivering')) ?></span>
      </button>
      <button class="tab-btn" onclick="showTab(event, 'delivered')">
        <i class="fas fa-check-circle"></i>
        <span>Delivered</span>
        <span class="order-count"><?= count(getOrdersByStatus($pdo, 'delivered')) ?></span>
      </button>
    </div>
    <div class="tab-indicator"></div>
  </div>
</div>

<div class="content-container">
<div id="pending" class="tab-content active">
  <div class="order-container">
    <?php
    $orders = $pdo->query("SELECT * FROM orders WHERE status = 'pending' ORDER BY created_at ASC")->fetchAll();
    foreach ($orders as $order):
      $stmtItems = $pdo->prepare("SELECT oi.item_id, oi.quantity, m.item_name 
                                  FROM order_items oi 
                                  JOIN menu_items m ON oi.item_id = m.id 
                                  WHERE oi.order_id = ?");
      $stmtItems->execute([$order['order_id']]);
      $items = $stmtItems->fetchAll();
    ?>
    <div class="order-card">
<h3>🧾 Order: <?= htmlspecialchars($order['order_id']) ?> 
    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
        <?= ucfirst(htmlspecialchars($order['status'])) ?>
    </span>
</h3>
      <ul>
      <?php foreach ($items as $i): ?>
        <li><?= htmlspecialchars($i['item_name']) ?> x <?= $i['quantity'] ?></li>
      <?php endforeach; ?>
      </ul>
      <strong>Total: RM <?= number_format($order['total'], 2) ?></strong>
      <div class="actions">
        <form action="approve_order.php" method="post" class="slide-form">
          <input type="hidden" name="id" value="<?= $order['id'] ?>">
          <div class="slide-to-confirm" data-form-id="<?= $order['id'] ?>">
            <span class="slide-hint">Slide to confirm</span>
            <div class="slider-button">
              <i class="fas fa-hourglass-half" style="margin-right: 10px;"></i>
              Preparing
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div id="preparing" class="tab-content active">
  <div class="order-container">
    <?php
    $orders = $pdo->query("SELECT * FROM orders WHERE status = 'preparing' ORDER BY created_at ASC")->fetchAll();
    foreach ($orders as $order):
      $stmtItems = $pdo->prepare("SELECT oi.item_id, oi.quantity, m.item_name 
                                  FROM order_items oi 
                                  JOIN menu_items m ON oi.item_id = m.id 
                                  WHERE oi.order_id = ?");
      $stmtItems->execute([$order['order_id']]);
      $items = $stmtItems->fetchAll();
    ?>
    <div class="order-card">
<h3>🧾 Order: <?= htmlspecialchars($order['order_id']) ?> 
    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
        <?= ucfirst(htmlspecialchars($order['status'])) ?>
    </span>
</h3>
      <ul>
      <?php foreach ($items as $i): ?>
        <li><?= htmlspecialchars($i['item_name']) ?> x <?= $i['quantity'] ?></li>
      <?php endforeach; ?>
      </ul>
      <strong>Total: RM <?= number_format($order['total'], 2) ?></strong>
      <div class="actions">
        <form action="preparing_order.php" method="post" class="slide-form">
          <input type="hidden" name="id" value="<?= $order['id'] ?>">
          <div class="slide-to-confirm" data-form-id="<?= $order['id'] ?>">
            <span class="slide-hint">Slide to confirm</span>
            <div class="slider-button">
              <i class="fas fa-hourglass-half" style="margin-right: 10px;"></i>
              Delivering
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div id="delivering" class="tab-content active">
  <div class="order-container">
    <?php
    $orders = $pdo->query("SELECT * FROM orders WHERE status = 'delivering' ORDER BY created_at ASC")->fetchAll();
    foreach ($orders as $order):
      $stmtItems = $pdo->prepare("SELECT oi.item_id, oi.quantity, m.item_name 
                                  FROM order_items oi 
                                  JOIN menu_items m ON oi.item_id = m.id 
                                  WHERE oi.order_id = ?");
      $stmtItems->execute([$order['order_id']]);
      $items = $stmtItems->fetchAll();
    ?>
    <div class="order-card">
  <h3>🧾 Order: <?= htmlspecialchars($order['order_id']) ?> 
    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
        <?= ucfirst(htmlspecialchars($order['status'])) ?>
    </span>
</h3>
      <ul>
      <?php foreach ($items as $i): ?>
        <li><?= htmlspecialchars($i['item_name']) ?> x <?= $i['quantity'] ?></li>
      <?php endforeach; ?>
      </ul>
      <strong>Total: RM <?= number_format($order['total'], 2) ?></strong>
      <div class="actions">
        <form action="delivering_order.php" method="post" class="slide-form">
          <input type="hidden" name="id" value="<?= $order['id'] ?>">
          <div class="slide-to-confirm" data-form-id="<?= $order['id'] ?>">
            <span class="slide-hint">Slide to confirm</span>
            <div class="slider-button">
              <i class="fas fa-hourglass-half" style="margin-right: 10px;"></i>
              Delivered
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div id="delivered" class="tab-content active">
  <div class="order-container">
    <?php
    $orders = $pdo->query("SELECT * FROM orders WHERE status = 'delivered' ORDER BY created_at ASC")->fetchAll();
    foreach ($orders as $order):
      $stmtItems = $pdo->prepare("SELECT oi.item_id, oi.quantity, m.item_name 
                                  FROM order_items oi 
                                  JOIN menu_items m ON oi.item_id = m.id 
                                  WHERE oi.order_id = ?");
      $stmtItems->execute([$order['order_id']]);
      $items = $stmtItems->fetchAll();
    ?>
    <div class="order-card">
<h3>🧾 Order: <?= htmlspecialchars($order['order_id']) ?> 
    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
        <?= ucfirst(htmlspecialchars($order['status'])) ?>
    </span>
</h3>
      <ul>
      <?php foreach ($items as $i): ?>
        <li><?= htmlspecialchars($i['item_name']) ?> x <?= $i['quantity'] ?></li>
      <?php endforeach; ?>
      </ul>
      <strong>Total: RM <?= number_format($order['total'], 2) ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
</div>

</div>

<script>
document.querySelectorAll('.slide-to-confirm').forEach(slide => {
  const slider = slide.querySelector('.slider-button');
  const form = slide.closest('form');

  let isDown = false;
  let startX, moved;

  slider.addEventListener('mousedown', (e) => {
    isDown = true;
    startX = e.clientX;
    moved = 0;
    slider.style.transition = 'none';
  });

  document.addEventListener('mousemove', (e) => {
    if (!isDown) return;
    moved = e.clientX - startX;
    if (moved < 0) moved = 0;
    if (moved > 170) moved = 170;
    slider.style.transform = `translateX(${moved}px)`;
  });

  document.addEventListener('mouseup', () => {
    if (!isDown) return;
    isDown = false;
    slider.style.transition = 'transform 0.3s ease';

    if (moved >= 90) {
      slider.style.transform = `translateX(170px)`;
      setTimeout(() => {
        form.submit();
      }, 300);
    } else {
      slider.style.transform = 'translateX(0)';
    }
  });
});

function showTab(event, tabId) {
  event.preventDefault();

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
  });

  document.querySelectorAll('.tab-content').forEach(content => {
    content.classList.remove('active');
  });

  event.currentTarget.classList.add('active');
  document.getElementById(tabId).classList.add('active');

  localStorage.setItem('activeTab', tabId); 
  updateIndicator(tabId);
}

document.addEventListener('DOMContentLoaded', function() {
  const savedTab = localStorage.getItem('activeTab') || 'pending';

  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

  const activeBtn = document.querySelector(`.tab-btn[onclick*="${savedTab}"]`);
  const activeTab = document.getElementById(savedTab);

  if (activeBtn && activeTab) {
    activeBtn.classList.add('active');
    activeTab.classList.add('active');
  }

  updateIndicator(savedTab);
});

</script>
</div>


</body>
</html>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>

        .footer-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 70px;
            background:rgb(30, 26, 32);
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .nav-item svg {
            width: 32px;
            height: 32px;
            stroke:rgb(255, 220, 93);
            transition: all 0.3s ease;
        }

        .nav-label {
            font-family: 'Segoe UI', sans-serif;
            font-size: 12px;
            color:rgb(255, 220, 93);
            transition: color 0.3s ease;
        }

.nav-item:hover svg {
    stroke: var(--active-color);
}

.nav-item:hover .nav-label {
    color: var(--active-color);
}

        .nav-item.active svg {
            stroke: var(--active-color);
        }
        .nav-item.active .nav-label {
            color: var(--active-color);
        }

        .nav-item:hover {
            background:rgb(32, 32, 32);
            transform: translateY(-4px);
        }

.bz-text {
    font-size: 35px;
    font-weight: bold;
    fill: #ff6b6b;
    transition: all 0.3s ease;
}

/* Active Bz (clicked) */
.bz-item.active .bz-text {
    font-size: 18px;
    fill: var(--active-color);
}

/* 🔥 Hover effect: Brizo + shrink */
.bz-item:hover .bz-text {
    font-size: 18px;
    fill: var(--active-color);
}
</style>

</head>
<body>
    <nav class="footer-nav">

        <div class="nav-item bz-item" style="--active-color: #ff6b6b;" data-link="../Main Page/main_page.php">
            <svg viewBox="0 0 50 24">
                <text x="5" y="18" class="bz-text">Bz</text>
            </svg>
            <span class="nav-label">Menu</span>
        </div>

        <div class="nav-item other-item" style="--active-color: #ff9f43;" data-link="../Manage_Account/index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="nav-label">ALL Staff</span>
        </div>

<div class="nav-item other-item" style="--active-color: #27ae60;" data-link="../Order_Management/index.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="4" y="3" width="16" height="18" rx="2"></rect>
        <path d="M10 7h6"></path>
        <path d="M10 12h6"></path>
        <path d="M10 17h6"></path>
        <path d="M6 7l1 1 2-2"></path>
        <path d="M6 12l1 1 2-2"></path>
        <path d="M6 17l1 1 2-2"></path>
    </svg>
    <span class="nav-label">Manage Order</span>
</div>

<div class="nav-item other-item" style="--active-color: #3498db;" data-link="../Manage_Menu_Item/index.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="3" y="3" width="7" height="7" rx="1" ry="1" />
        <rect x="14" y="3" width="7" height="7" rx="1" ry="1" />
        <rect x="14" y="14" width="7" height="7" rx="1" ry="1" />
        <rect x="3" y="14" width="7" height="7" rx="1" ry="1" />
    </svg>
    <span class="nav-label">Menu Manage</span>
</div>

        <div class="nav-item other-item" style="--active-color: #8e44ad;" data-link="../More/more.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="12" cy="5" r="1"></circle>
                <circle cx="12" cy="19" r="1"></circle>
            </svg>
            <span class="nav-label">More</span>
        </div>
    </nav>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const navItems = document.querySelectorAll('.nav-item');
            const bzItem = document.querySelector('.bz-item');
            const bzText = bzItem?.querySelector('.bz-text');
    
            const ACTIVE_KEY = 'activeNav'; // localStorage key
    
            // 🔁 On load: restore active state
            const savedLabel = localStorage.getItem(ACTIVE_KEY);
            if (savedLabel) {
                navItems.forEach(item => {
                    const label = item.querySelector('.nav-label')?.textContent.trim();
                    if (label === savedLabel) {
                        item.classList.add('active');
                        if (bzText) {
                            bzText.textContent = (item === bzItem) ? 'Brizo' : 'Bz';
                        }
                    }
                });
            }
    
            function handleNavClick(event) {
                const clickedItem = event.currentTarget;
    
                // Clear all active states
                navItems.forEach(item => item.classList.remove('active'));
    
                // Activate clicked
                clickedItem.classList.add('active');
    
                // Update Bz logic
                if (bzText) {
                    bzText.textContent = (clickedItem === bzItem) ? 'Brizo' : 'Bz';
                }
// 💾 Save active label to localStorage
const label = clickedItem.querySelector('.nav-label')?.textContent.trim();
if (label) {
    localStorage.setItem(ACTIVE_KEY, label);
}


// 🚀 Redirect if data-link present
const targetLink = clickedItem.getAttribute('data-link');
if (targetLink) {
    window.location.href = targetLink;
}

            }
            
            if (bzItem && bzText) {
    bzItem.addEventListener('mouseenter', () => {
        bzText.textContent = 'Brizo';
    });

    bzItem.addEventListener('mouseleave', () => {
        // Only revert if not active
        if (!bzItem.classList.contains('active')) {
            bzText.textContent = 'Bz';
        }
    });
}

            // Attach handlers
            navItems.forEach(item => {
                item.addEventListener('click', handleNavClick);
                item.addEventListener('touchstart', handleNavClick, { passive: true });
            });
        });
    </script>


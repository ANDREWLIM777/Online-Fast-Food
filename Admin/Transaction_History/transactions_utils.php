<?php
// Transaction_History/index.php
require 'db_conn.php';
session_start();

function getYears($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM orders ORDER BY year DESC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$years = getYears($pdo);
$today = $_GET['date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Transaction History</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #121212;
      color: #eee;
      margin: 0;
      padding: 2rem;
    }
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
    }
    .back-btn {
      background: linear-gradient(to right, #c0a23d, #e8d48b);
      color: #000;
      font-weight: bold;
      padding: 10px 20px;
      border-radius: 10px;
      text-decoration: none;
      transition: 0.2s ease;
    }
    .back-btn:hover {
      background: #e8d48b;
      box-shadow: 0 0 10px #e8d48b;
    }
    h1 {
      flex: 1;
      text-align: center;
      color: #c0a23d;
      font-size: 2.3rem;
    }
    .tabs {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-bottom: 2rem;
    }
    .tab-btn {
      background: #252525;
      color: #e8d48b;
      border: none;
      padding: 10px 30px;
      font-size: 1rem;
      cursor: pointer;
      border-bottom: 3px solid transparent;
    }
    .tab-btn.active {
      border-bottom: 3px solid gold;
      font-weight: bold;
    }
    .filter {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-bottom: 1rem;
    }
    select {
      padding: 8px 10px;
      border-radius: 6px;
      background: #252525;
      color: #fff;
      border: 1px solid #444;
    }
    .transaction-card {
      background: #1c1c1c;
      padding: 1rem;
      border-radius: 10px;
      border-left: 5px solid #c0a23d;
      max-width: 800px;
      margin: 1rem auto;
      box-shadow: 0 0 15px rgba(255, 215, 0, 0.15);
    }
    .transaction-card h3 {
      margin: 0;
      color: #f0e68c;
    }
    .transaction-meta {
      font-size: 0.95rem;
      color: #bbb;
      margin-top: 5px;
    }
    .view-btn {
      margin-top: 10px;
      display: inline-block;
      background: #e8d48b;
      color: #000;
      padding: 6px 14px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="header">
    <a href="../Main Page/main_page.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    <h1>📄 Transaction History</h1>
    <div style="width: 120px;"></div>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('orders')">Order History</button>
    <button class="tab-btn" onclick="switchTab('refunds')">Refund History</button>
  </div>

  <div class="filter">
    <select id="yearFilter" onchange="filterByDate()">
      <option value="">Year</option>
      <?php foreach ($years as $year): ?>
        <option value="<?= $year ?>"><?= $year ?></option>
      <?php endforeach; ?>
    </select>
    <select id="monthFilter" onchange="filterByDate()">
      <option value="">Month</option>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= sprintf('%02d', $m) ?>"><?= date('F', mktime(0,0,0,$m,10)) ?></option>
      <?php endfor; ?>
    </select>
    <select id="dayFilter" onchange="filterByDate()">
      <option value="">Day</option>
      <?php for ($d = 1; $d <= 31; $d++): ?>
        <option value="<?= sprintf('%02d', $d) ?>"><?= $d ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <div id="orders" class="tab-content active">
    <?php
    $stmt = $pdo->prepare("SELECT o.*, c.fullname FROM orders o JOIN customers c ON o.customer_id = c.id WHERE DATE(o.created_at) = ? AND o.status != 'pending'");
    $stmt->execute([$today]);
    $orders = $stmt->fetchAll();
    foreach ($orders as $o):
    ?>
      <div class="transaction-card">
        <h3><?= htmlspecialchars($o['order_code']) ?> | RM <?= number_format($o['total'], 2) ?></h3>
        <div class="transaction-meta">
          <?= htmlspecialchars($o['fullname']) ?> | <?= date('Y-m-d H:i', strtotime($o['created_at'])) ?>
        </div>
        <a href="view_order.php?id=<?= $o['id'] ?>" class="view-btn"><i class="fas fa-eye"></i> View</a>
      </div>
    <?php endforeach; ?>
  </div>

  <div id="refunds" class="tab-content" style="display:none">
    <?php
    $stmt = $pdo->prepare("SELECT r.*, c.fullname FROM refund_requests r JOIN orders o ON r.order_id = o.order_code JOIN customers c ON o.customer_id = c.id WHERE DATE(r.date) = ?");
    $stmt->execute([$today]);
    $refunds = $stmt->fetchAll();
    foreach ($refunds as $r):
    ?>
      <div class="transaction-card">
        <h3><?= htmlspecialchars($r['order_id']) ?> | Status: <?= htmlspecialchars($r['status']) ?></h3>
        <div class="transaction-meta">
          <?= htmlspecialchars($r['fullname']) ?> | <?= date('Y-m-d H:i', strtotime($r['date'])) ?>
        </div>
        <a href="view_refund.php?id=<?= $r['id'] ?>" class="view-btn"><i class="fas fa-eye"></i> View</a>
      </div>
    <?php endforeach; ?>
  </div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
  document.querySelector(`[onclick*="${tab}"]`).classList.add('active');
  document.getElementById(tab).style.display = 'block';
}
function filterByDate() {
  const year = document.getElementById('yearFilter').value;
  const month = document.getElementById('monthFilter').value;
  const day = document.getElementById('dayFilter').value;
  const date = `${year}-${month || '01'}-${day || '01'}`;
  const today = new Date().toISOString().split('T')[0];
  if (date > today) return alert("Cannot search future dates.");
  window.location.href = `index.php?date=${date}`;
}
</script>
</body>
</html>

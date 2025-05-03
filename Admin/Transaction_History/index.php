<?php
require 'db_conn.php';
session_start();

function getYears($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT YEAR(created_at) as year 
        FROM (
            SELECT created_at FROM orders
            UNION ALL
            SELECT created_at FROM refund_requests
        ) AS combined 
        ORDER BY year DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$years = getYears($pdo);
$selectedDate = $_GET['date'] ?? null;
$refundRequests = $pdo->query("SELECT * FROM refund_requests WHERE status = 'approved' ORDER BY created_at DESC LIMIT 20")->fetchAll();


// 查询已完成订单
if ($selectedDate) {
    $stmt = $pdo->prepare("SELECT o.*, c.fullname FROM orders o 
                           JOIN customers c ON o.customer_id = c.id 
                           WHERE DATE(o.created_at) = ? AND o.status = 'completed'
                           ORDER BY o.created_at DESC");
    $stmt->execute([$selectedDate]);
} else {
    $stmt = $pdo->prepare("SELECT o.*, c.fullname FROM orders o 
                           JOIN customers c ON o.customer_id = c.id 
                           WHERE o.status = 'completed'
                           ORDER BY o.created_at DESC LIMIT 20");
    $stmt->execute();
}
$orders = $stmt->fetchAll();

// 查询已审核通过的退款请求（refund_requests.status = 'approved'）
if ($selectedDate) {
    $stmt = $pdo->prepare("
        SELECT r.id AS refund_id,r.customer_id, r.order_id, r.created_at AS refund_date, r.status AS refund_status, 
               r.reason, r.details, r.evidence_path, r.admin_notes, c.fullname 
        FROM refund_requests r
        LEFT JOIN orders o ON r.order_id = o.order_id
        LEFT JOIN customers c ON r.customer_id = c.id
        WHERE DATE(r.created_at) = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$selectedDate]);
} else {
    $stmt = $pdo->query("
        SELECT r.id AS refund_id, r.customer_id, r.order_id, r.created_at AS refund_date, r.status AS refund_status, 
               r.reason, r.details, r.evidence_path, r.admin_notes, c.fullname 
        FROM refund_requests r
        LEFT JOIN orders o ON r.order_id = o.order_id
        LEFT JOIN customers c ON r.customer_id = c.id
        WHERE r.status = 'approved'
        ORDER BY r.created_at DESC LIMIT 20
    ");
}
$refundRequests = $stmt->fetchAll();


// 查询订单中 status = refunded 的记录
if ($selectedDate) {
    $stmt = $pdo->prepare("SELECT o.id AS order_id, o.order_id, o.total, o.created_at AS refund_date, o.status AS refund_status, NULL AS reason, c.fullname 
                           FROM orders o
                           JOIN customers c ON o.customer_id = c.id
                           WHERE DATE(o.created_at) = ? AND o.status = 'refunded'
                           ORDER BY o.created_at DESC");
    $stmt->execute([$selectedDate]);
} else {
    $stmt = $pdo->prepare("SELECT o.id AS order_id, o.order_id, o.total, o.created_at AS refund_date, o.status AS refund_status, NULL AS reason, c.fullname 
                           FROM orders o
                           JOIN customers c ON o.customer_id = c.id
                           WHERE o.status = 'refunded'
                           ORDER BY o.created_at DESC LIMIT 20");
    $stmt->execute();
}
$refundOrders = $stmt->fetchAll();


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
      padding: 2rem;
    }

                    /* 背景发光环 */
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
  z-index: -1; /* ⬅ 放底层 */
}

/* 星尘粒子 */
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
  z-index: -2; /* ⬅ 更底层 */
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

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }
    .back-btn {
      display: inline-block;
      background: linear-gradient(to right, #c0a23d, #e8d48b);
      color: #000;
      font-weight: bold;
      padding: 10px 20px;
      border-radius: 10px;
      text-decoration: none;
      transition: 0.2s ease;
      margin-bottom: 2rem;
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

    .tab-page { display: none; }
    .tab-page.active { display: block; }
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

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
  <a href="../Main Page/main_page.php" class="back-btn">
    <i class="fas fa-house"></i> Back To Main Page
  </a>
    <h1>📄 Transaction History</h1>
    <div style="width: 0px;"></div>
    <div style="text-align: center; margin-bottom: 1rem;">
    <span style="color: #4CAF50">Approved Refunds: <?= count($refundRequests) ?></span> | 
    <span style="color: #FF5722">Refunded Orders: <?= count($refundOrders) ?></span>
</div>
</div>

<div class="tabs">
    <button class="tab-btn" onclick="switchTab('orders')">Order History</button>
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
<?php foreach ($orders as $o): ?>
    <div class="transaction-card">
        <h3><?= htmlspecialchars($o['order_id']) ?> | RM <?= number_format($o['total'], 2) ?></h3>
        <div class="transaction-meta"><?= htmlspecialchars($o['fullname']) ?> | <?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></div>
        <a href="view_order.php?id=<?= $o['id'] ?>" class="view-btn"><i class="fas fa-eye"></i> View</a>
    </div>
<?php endforeach; ?>
</div>

<div id="refunds" class="tab-content" style="display:none">
<?php foreach ($refundRequests as $r): ?>
    <div class="transaction-card" style="border-left-color: #4CAF50;">
        <h3><?= htmlspecialchars($r['order_id']) ?> | 
            Status: <span style="color: #4CAF50">APPROVED</span>
        </h3>
        <div class="transaction-meta">
            <?= htmlspecialchars($r['fullname'] ?? 'Unknown') ?> | 
            <?= date('Y-m-d H:i', strtotime($r['refund_date'])) ?>
        </div>
        <p><strong>Reason:</strong> <?= htmlspecialchars($r['reason']) ?></p>
        <?php if (!empty($r['details'])): ?>
            <p><strong>Details:</strong> <?= nl2br(htmlspecialchars($r['details'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($r['evidence_path'])): ?>
            <p><strong>Evidence:</strong><br>
            <img src="<?= htmlspecialchars($r['evidence_path']) ?>" style="max-width: 300px;">
            </p>
        <?php endif; ?>
        <a href="view_refund.php?id=<?= $r['refund_id'] ?>" class="view-btn">
            <i class="fas fa-eye"></i> View
        </a>
    </div>
<?php endforeach; ?>



    <?php foreach ($refundOrders as $o): ?>
        <div class="transaction-card" style="border-left-color: #FF5722;">
            <h3><?= htmlspecialchars($o['order_id']) ?> | 
                Status: <span style="color: #FF5722">REFUNDED</span>
            </h3>
            <div class="transaction-meta">
                <?= htmlspecialchars($o['fullname']) ?> | 
                <?= date('Y-m-d H:i', strtotime($o['refund_date'])) ?>
            </div>
            <a href="view_refund.php?id=<?= $o['order_id'] ?>" class="view-btn">
                <i class="fas fa-eye"></i> View
            </a>
        </div>
    <?php endforeach; ?>
</div>


<script>
// 修改后的 switchTab 函数
function switchTab(tab) {
  // 使用平滑滚动到顶部
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });

  // 使用 URL 参数代替 hash
  const url = new URL(window.location);
  url.searchParams.set('tab', tab);
  window.history.replaceState(null, '', url);

  // 原有切换逻辑保持不变
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.onclick.toString().includes(tab));
  });
  
  document.querySelectorAll('.tab-content').forEach(content => {
    content.style.display = content.id === tab ? 'block' : 'none';
  });
}

// 修改页面加载逻辑
window.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const targetTab = urlParams.get('tab') || 'orders';
  
  // 延迟执行确保 DOM 加载完成
  setTimeout(() => {
    switchTab(targetTab);
    window.scrollTo(0, 0); // 强制回顶
  }, 50);
});

function filterByDate() {
  const year = document.getElementById('yearFilter').value;
  const month = document.getElementById('monthFilter').value;
  const day = document.getElementById('dayFilter').value;

  if (!year || !month || !day) return;

  const date = `${year}-${month}-${day}`;
  const today = new Date().toISOString().split('T')[0];

  if (date > today) {
    alert("Cannot select future date.");
    return;
  }

  window.location.href = `index.php?date=${date}`;
}
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        /* åºé¨å¯¼èªå®¹å¨ */
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
/*#fffbed; */
        /* å¯¼èªé¡¹ */
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        /* icon é¢è² */
        .nav-item svg {
            width: 32px;
            height: 32px;
            stroke:rgb(255, 220, 93);
            transition: all 0.3s ease;
        }

        /* é»è®¤ææ¬é¢è² */
        .nav-label {
            font-family: 'Segoe UI', sans-serif;
            font-size: 12px;
            color:rgb(255, 220, 93);
            transition: color 0.3s ease;
        }
/* #636e72;*/
        /* 🖱️ Hover effect with color */
.nav-item:hover svg {
    stroke: var(--active-color);
}

.nav-item:hover .nav-label {
    color: var(--active-color);
}

        /* éä¸­ç¶æ */
        .nav-item.active svg {
            stroke: var(--active-color);
        }
        .nav-item.active .nav-label {
            color: var(--active-color);
        }

        /* æ¬åææ */
        .nav-item:hover {
            background:rgb(32, 32, 32);
            transform: translateY(-4px);
        }
/* #fafaf8db; */
/* Default Bz style */
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
    <!-- åºé¨å¯¼èªæ  -->
    <nav class="footer-nav">
        <!-- Bz èå -->
        <div class="nav-item bz-item" style="--active-color: #ff6b6b;" data-link="../Main Page/main_page.php">
            <svg viewBox="0 0 50 24">
                <text x="5" y="18" class="bz-text">Bz</text>
            </svg>
            <span class="nav-label">Menu</span>
        </div>

        <!-- æä½³åå·¥ -->
        <div class="nav-item other-item" style="--active-color: #ff9f43;" data-link="../Manage_Account/index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="nav-label">ALL Staff</span>
        </div>

<!-- è®¢åç®¡çï¼Checklist å¾æ ï¼ -->
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

<!-- 菜单管理方式 -->
<div class="nav-item other-item" style="--active-color: #3498db;" data-link="../Manage_Menu_Item/index.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="3" y="3" width="7" height="7" rx="1" ry="1" />
        <rect x="14" y="3" width="7" height="7" rx="1" ry="1" />
        <rect x="14" y="14" width="7" height="7" rx="1" ry="1" />
        <rect x="3" y="14" width="7" height="7" rx="1" ry="1" />
    </svg>
    <span class="nav-label">Menu Manage</span>
</div>

        <!-- æ´å¤éé¡¹ -->
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


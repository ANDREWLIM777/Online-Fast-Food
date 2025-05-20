<?php
require 'db_conn.php';
session_start();

// 获取订单年份
function getYears($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM orders ORDER BY year DESC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$years = getYears($pdo);
$selectedDate = $_GET['date'] ?? null;

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">

    <style>
        /* header 样式（来自你提供的代码） */
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
            padding: 1.8rem 0;
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
        }

        .sub-title::before {
            left: -30px;
            transform: translateY(-50%) rotate(-15deg);
        }

        .sub-title::after {
            right: -30px;
            transform: translateY(-50%) rotate(15deg);
        }

        .header::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 50% 50%, #f4e3b210 0%, transparent 60%);
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

        /* 日期查询样式 */
        .date-controls {
            margin-top: 130px;
            margin-left: 50px;
            text-align: center;
            font-family: 'Roboto', sans-serif;
        }

        .date-controls button {
            margin: 0 5px;
            padding: 10px 15px;
            background-color: #c0a23d;
            border: none;
            color: black;
            border-radius: 4px;
            cursor: pointer;
        }

        .date-controls button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .current-date {
            font-size: 1.2rem;
            font-weight: bold;
            color: #c0a23d;
        }

        body {
            font-family: Arial, sans-serif;
            background: #121212;
            color: #eee;
            padding: 2rem;
            padding-bottom: 60px;
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

        h1 {
            text-align: center;
            color: #c0a23d;
            font-size: 2.3rem;
            margin-bottom: 2rem;
        }

        .filter {
            display: flex;
            justify-content: center;
            margin-top:10px;
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

        #messageBox {
    position: fixed;
    top: 100px;
    right: 30px;
    background: #ffc107;
    color: #000;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: bold;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.4s ease, transform 0.4s ease;
    transform: translateY(-20px);
    pointer-events: none;
}

#messageBox.show {
    opacity: 1;
    transform: translateY(0);
}

    </style>
</head>
<body>


  <div class="header">

        <div class="title-group">
            <div class="main-title">BRIZO MELAKA</div>
            <div class="sub-title">Transaction History Page</div>
        </div>

        <a href="../Main Page/main_page.php" class="back-btn">
        <i class="fas fa-house"></i> Back To Main Page
    </a>

    </div>


<div class="date-controls">
        <button id="prevBtn">Previous Day</button>
        <span class="current-date" id="currentDate"></span>
        <button id="nextBtn">Next Day</button>
        <button id="todayBtn">Today</button>
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

<div>
    <?php foreach ($orders as $o): ?>
        <div class="transaction-card">
            <h3><?= htmlspecialchars($o['order_id']) ?> | RM <?= number_format($o['total'], 2) ?></h3>
            <div class="transaction-meta"><?= htmlspecialchars($o['fullname']) ?> | <?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></div>
            <a href="view_order.php?id=<?= $o['id'] ?>" class="view-btn"><i class="fas fa-eye"></i> View</a>
        </div>
    <?php endforeach; ?>
</div>

<script>

const currentDateSpan = document.getElementById("currentDate");
const prevBtn = document.getElementById("prevBtn");
const nextBtn = document.getElementById("nextBtn");
const todayBtn = document.getElementById("todayBtn");

// 从 URL 中获取初始日期
const urlParams = new URLSearchParams(window.location.search);
let currentDate = urlParams.get('date') ? new Date(urlParams.get('date')) : new Date();

// 清除时间部分
currentDate.setHours(0, 0, 0, 0);

// 工具函数：格式化日期为 yyyy-mm-dd
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// 更新顶部显示
function updateDateDisplay() {
    const formatted = formatDate(currentDate);
    currentDateSpan.textContent = formatted;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // 禁用 next 按钮如果今天了
    nextBtn.disabled = formatDate(currentDate) === formatDate(today);
}

// 跳转到指定日期
function navigateTo(date) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (date > today) {
        alert("Cannot select future date.");
        return;
    }

    const target = formatDate(date);
    window.location.href = `index.php?date=${target}`;
}

// 按钮事件处理
prevBtn.addEventListener("click", () => {
    currentDate.setDate(currentDate.getDate() - 1);
    navigateTo(currentDate);
});

nextBtn.addEventListener("click", () => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const next = new Date(currentDate);
    next.setDate(currentDate.getDate() + 1);

    if (next > today) {
        showMessage("Cannot select a future date.");
        return;
    }

    currentDate = next;
    navigateTo(currentDate);
});

todayBtn.addEventListener("click", () => {
    currentDate = new Date();
    currentDate.setHours(0, 0, 0, 0);
    navigateTo(currentDate);
});

// 过滤器（下拉选择）
function filterByDate() {
    const year = document.getElementById('yearFilter').value;
    const month = document.getElementById('monthFilter').value;
    const day = document.getElementById('dayFilter').value;

    if (!year || !month || !day) return;

    const selected = new Date(`${year}-${month}-${day}`);
    selected.setHours(0, 0, 0, 0);

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (selected > today) {
        showMessage("Cannot select a future date.");
        return;
    }

    function showMessage(msg) {
    const box = document.getElementById('messageBox');
    box.textContent = '⚠️ ' + msg;
    box.classList.add('show');

    // 自动隐藏
    setTimeout(() => {
        box.classList.remove('show');
    }, 3000);
}


    navigateTo(selected);
}

// 初始化显示
updateDateDisplay();
</script>
<div id="messageBox" class="hidden">⚠️ Cannot select a future date.</div>
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


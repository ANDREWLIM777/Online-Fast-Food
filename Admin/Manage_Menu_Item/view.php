<?php
require 'db_connect.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $item = $stmt->fetch();
    
    if (!$item) {
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="content-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>View Item</h1>
                <a href="index.php" class="btn back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </header>

        <div class="detail-container">
            <div class="detail-card">
                <div class="card-header">
                    <h2><?= htmlspecialchars($item['name']) ?></h2>
                    <span class="price-badge">$<?= number_format($item['price'], 2) ?></span>
                </div>
                
                <div class="card-body">
                    <p class="description"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                    <div class="meta-info">
                        <div class="status-indicator <?= $item['is_available'] ? 'available' : 'unavailable' ?>">
                            Status: <?= $item['is_available'] ? 'Available' : 'Unavailable' ?>
                        </div>
                        <?php if($item['promotion']): ?>
                        <div class="promo-tag">
                            Promotion: <?= htmlspecialchars($item['promotion']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="edit.php?id=<?= $item['id'] ?>" class="btn edit-btn">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="index.php" class="btn back-btn">
                        <i class="fas fa-list"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!DOCTYPE html>
<html lang="zh">
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
            background: #fffbed;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
        }

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
            stroke: #636e72;
            transition: all 0.3s ease;
        }

        /* é»è®¤ææ¬é¢è² */
        .nav-label {
            font-family: 'Segoe UI', sans-serif;
            font-size: 12px;
            color: #636e72;
            transition: color 0.3s ease;
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
            background: #f8f9fa;
            transform: translateY(-4px);
        }

        /* Bz å­ä½æ ·å¼ */
        .bz-text {
            font-size: 35px;
            font-weight: bold;
            fill: #ff6b6b;
            transition: all 0.3s ease;
        }

        /* Brizo ç¶æ */
        .bz-item.active .bz-text {
            font-size: 18px;
            fill: var(--active-color);
        }
    </style>
</head>
<body>
    <!-- åºé¨å¯¼èªæ  -->
    <nav class="footer-nav">
        <!-- Bz èå -->
        <div class="nav-item bz-item" style="--active-color: #ff6b6b;"data-link="../Main Page/main_page.html">
            <svg viewBox="0 0 50 24">
                <text x="5" y="18" class="bz-text">Bz</text>
            </svg>
            <span class="nav-label">Menu</span>
        </div>

        <!-- æä½³åå·¥ -->
        <div class="nav-item other-item" style="--active-color: #ff9f43;"data-link="fooy.html">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="nav-label">Best Staff</span>
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
        <!-- èç³»æ¹å¼ -->
        <div class="nav-item other-item" style="--active-color: #3498db;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg>
            <span class="nav-label">Contact</span>
        </div>

        <!-- æ´å¤éé¡¹ -->
        <div class="nav-item other-item" style="--active-color: #8e44ad;">
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
            const bzText = bzItem.querySelector('.bz-text');
    
            // 统一事件处理函数
            function handleNavigation(event) {
                event.preventDefault(); // 阻止默认行为
                const target = event.currentTarget;
                
                // 更新激活状态
                navItems.forEach(item => item.classList.remove('active'));
                target.classList.add('active');
                
                // 更新Bz文字
                bzText.textContent = target === bzItem ? 'Brizo' : 'Bz';
                
                // 执行页面跳转
                const targetPage = target.dataset.link;
                if (targetPage) {
                    window.location.href = targetPage;
                }
            }
    
            // 绑定事件监听器
            navItems.forEach(item => {
                item.addEventListener('click', handleNavigation);
                item.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    handleNavigation(e);
                }, { passive: false });
            });
    
            // 页面加载时自动激活当前项
            const currentPage = window.location.pathname.split('/').pop();
            navItems.forEach(item => {
                if (item.dataset.link === currentPage) {
                    item.classList.add('active');
                    if(item.classList.contains('bz-item')) {
                        item.querySelector('.bz-text').textContent = 'Brizo';
                    }
                }
            });
        });
    </script>

</body>
</html>
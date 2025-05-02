<?php
require 'db_conn.php';
include '../../auth_notifications.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 查询通知 + 创建者信息
$stmt = $pdo->query("
    SELECT n.*, 
           a.name AS creator_name, 
           r.name AS reposter_name 
    FROM notifications n
    JOIN admin a ON n.created_by = a.id
    LEFT JOIN admin r ON n.reposted_by = r.id
    ORDER BY is_pinned DESC, created_at DESC
");
$notifications = $stmt->fetchAll();

$isSuperAdmin = ($_SESSION['user_role'] ?? '') === 'superadmin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f0f0f;
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

        h1 {
            color: #c0a23d;
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .back-btn {
            display: inline-block;
            background: none;
            color: #e8d48b;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            border: 1px solid #c0a23d;
            padding: 8px 16px;
            border-radius: 10px;
            margin-bottom: 0rem;
        }

        .back-btn:hover {
            background: #c0a23d;
            color: #000;
        }

        .notification {
            background: #1c1c1c;
            border-left: 5px solid #c0a23d;
            padding: 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .notification.pinned {
            border-left-color: gold;
            background: #2b250a;
        }

        .notification h2 {
            margin-top: 0;
            color: #f0e68c;
        }

        .meta {
            font-size: 0.9rem;
            color: #aaa;
            margin-top: 8px;
        }

        .admin-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .admin-actions a {
            color: #ccc;
            margin-left: 10px;
            text-decoration: none;
        }

        .admin-actions a:hover {
            color: #fff;
        }

        .new-btn {
            display: inline-block;
            background: linear-gradient(to right, #c0a23d, #e8d48b);
            padding: 0.5rem 1rem;
            color: #000;
            text-decoration: none;
            font-weight: bold;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .new-btn:hover {
            background: #e8d48b;
        }
    </style>
</head>
<body>

    <a class="back-btn" href="../more.php"><i class="fas fa-arrow-left"></i> Back to More Page</a>

    <h1>📢 Notifications</h1>

    <?php if ($isSuperAdmin): ?>
        <a class="new-btn" href="create.php"><i class="fas fa-plus"></i> New Notification</a>
    <?php endif; ?>

    <?php foreach ($notifications as $n): ?>
        <div class="notification <?= $n['is_pinned'] ? 'pinned' : '' ?>">
            <h2><?= htmlspecialchars($n['title']) ?></h2>
            <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>

            <div class="meta">
                Posted by <?= htmlspecialchars($n['creator_name']) ?> on <?= date('M d, Y H:i', strtotime($n['created_at'])) ?>
                <?php if (!empty($n['reposted_by'])): ?>
                    <br><strong style="color: gold;">
                        Repost by <?= htmlspecialchars($n['reposter_name']) ?> on <?= date('M d, Y H:i', strtotime($n['reposted_at'])) ?>
                    </strong>
                <?php endif; ?>
            </div>

            <?php if ($isSuperAdmin): ?>
                <div class="admin-actions">
                    <a href="pin.php?id=<?= $n['id'] ?>&act=<?= $n['is_pinned'] ? 'unpin' : 'pin' ?>">
                        <i class="fas fa-thumbtack"></i>
                    </a>
                    <a href="edit.php?id=<?= $n['id'] ?>"><i class="fas fa-edit"></i></a>
                    <a href="delete.php?id=<?= $n['id'] ?>"><i class="fas fa-trash"></i></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
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
        <div class="nav-item bz-item" style="--active-color: #ff6b6b;" data-link="../../Main Page/main_page.php">
            <svg viewBox="0 0 50 24">
                <text x="5" y="18" class="bz-text">Bz</text>
            </svg>
            <span class="nav-label">Menu</span>
        </div>

        <!-- æä½³åå·¥ -->
        <div class="nav-item other-item" style="--active-color: #ff9f43;" data-link="../../Manage_Account/index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="nav-label">ALL Staff</span>
        </div>

<!-- è®¢åç®¡çï¼Checklist å¾æ ï¼ -->
<div class="nav-item other-item" style="--active-color: #27ae60;" data-link="../../Order_Management/index.php">
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
<div class="nav-item other-item" style="--active-color: #3498db;" data-link="../../Manage_Menu_Item/index.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <rect x="3" y="3" width="7" height="7" rx="1" ry="1" />
        <rect x="14" y="3" width="7" height="7" rx="1" ry="1" />
        <rect x="14" y="14" width="7" height="7" rx="1" ry="1" />
        <rect x="3" y="14" width="7" height="7" rx="1" ry="1" />
    </svg>
    <span class="nav-label">Menu Manage</span>
</div>

        <!-- æ´å¤éé¡¹ -->
        <div class="nav-item other-item" style="--active-color: #8e44ad;" data-link="../../More/more.php">
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

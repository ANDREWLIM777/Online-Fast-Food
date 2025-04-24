<?php
require_once 'db_connect.php';
include '../auth.php';

// 搜索和分类处理
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';

// 构建高级查询
$query = "SELECT *, 
            MATCH(item_name, description) AGAINST(:search IN BOOLEAN MODE) AS relevance
          FROM menu_items";
$params = [];
$conditions = [];

// 始终绑定 :search 参数，无论 $search 是否为空
$params[':search'] = $search !== '' ? $search . '*' : '';

if($category !== 'all') {
    $conditions[] = "category = :category";
    $params[':category'] = $category;
}

if(!empty($search)) {
    $conditions[] = "MATCH(item_name, description) AGAINST(:search IN BOOLEAN MODE)";
}

if(!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY relevance DESC, created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- 新增字体 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">
    <style><?php include 'style.css'; ?></style>

</head>
<body>

<div class="header">
<div class="profile-container">
<div class="profile-icon" onclick="toggleProfile()">
    <i class="fas fa-user"></i>
</div>
<div class="profile-dropdown" id="profileDropdown">
    <div class="profile-header">
        <div class="profile-name"><?= strtoupper($_SESSION['user_name']); ?></div>
        <div class="profile-role"><?= strtolower($_SESSION['user_role']); ?></div>
    </div>
    <hr>
    <a href="../Admin_Account/profile.php"><i class="fas fa-user-edit"></i> Edit Your Profile</a>
    <a href="../Admin_Account/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
</div>
        <div class="title-group">
            <div class="main-title">BRIZO MELAKA</div>
            <div class="sub-title">Manage Menu Page</div>
        </div>
    </div>


<div class="admin-container">
        <main class="dashboard">
            <!-- 新版控制面板 -->
            <div class="control-panel">
            <div class="search-box">
                <div class="filter-group">
                <select id="categoryFilter" class="luxury-select">
    <?php
    $categories = ['all' => 'All Categories', 'burger' => '🍔 Burgers', 'chicken' => '🍗 Chicken', 'drink' => '🥤 Drinks', 'snacks' => '🍩 Snacks', 'meal' => '🍱 Meals'];
    foreach ($categories as $value => $label):
        $isSelected = $value === $category ? 'selected' : '';
        echo "<option value=\"$value\" $isSelected>$label</option>";
    endforeach;
    ?>
</select>
                </div>
                <a href="add.php" class="premium-3d-button">
                    <div class="button-content">
                        <i class="fas fa-plus"></i>
                        <span>New Item</span>
                    </div>
                    <div class="button-shine"></div>
                </a>
            </div>
            <div class="card-grid">
                <?php foreach($items as $item): ?>
                <div class="neo-card" data-id="<?= $item['id'] ?>">
                    <div class="card-media">
                        <div class="image-overlay"></div>
                        <?php if($item['photo']): ?>
                        <img src="<?= htmlspecialchars($item['photo']) ?>" 
                             alt="<?= htmlspecialchars($item['item_name']) ?>"
                             class="hover-zoom">
                        <?php endif; ?>
                        <div class="availability <?= $item['is_available'] ? 'available' : 'unavailable' ?>">
    <i class="fas fa-<?= $item['is_available'] ? 'check' : 'times' ?>"></i>
</div>

                        <span class="category-tag <?= $item['category'] ?>">
                            <?= ucfirst($item['category']) ?>
                        </span>
                    </div>
                    <div class="card-content">
                        <h3 class="item-title"><?= htmlspecialchars($item['item_name']) ?></h3>
                        <p class="item-desc"><?= htmlspecialchars($item['description']) ?></p>
                        <div class="price-row">
                            <div class="price-bubble">
                                $<?= number_format($item['price'], 2) ?>
                                <?php if($item['promotion']): ?>
                                <span class="promo-flag"><?= htmlspecialchars($item['promotion']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="action-buttons">
                                <a href="edit.php?id=<?= $item['id'] ?>" class="icon-btn edit-btn">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <button class="icon-btn delete-btn" data-id="<?= $item['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    <script>
function toggleProfile() {
    const dropdown = document.getElementById("profileDropdown");
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

// 点击其他区域时关闭 dropdown
window.onclick = function(event) {
    const profileContainer = document.querySelector('.profile-container');
    const dropdown = document.getElementById("profileDropdown");
    
    if (!profileContainer.contains(event.target)) {
        dropdown.style.display = "none";
    }
};
</script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="scripts.js"></script>
</body>
</html>



<!-- Menu Icon -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        .menu-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        .menu-icon {
            cursor: pointer;
            width: 30px;
            height: 24px;
            position: relative;
            transition: all 0.3s ease;
        }

        .menu-icon span {
            position: absolute;
            height: 3px;
            width: 100%;
            background:rgb(192, 168, 61);
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .menu-icon span:nth-child(1) { top: 0; }
        .menu-icon span:nth-child(2) { top: 10px; }
        .menu-icon span:nth-child(3) { top: 20px; }

        .menu-icon:hover span {
            background: #eace7c; /* 悬停亮金色 */
        }

        .menu-icon.active span {
            background: #c0a23d;
            box-shadow: 0 0 8px rgba(192,162,61,0.3);
        }

        .menu-icon.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .menu-icon.active span:nth-child(2) { opacity: 0; }

        .menu-icon.active span:nth-child(3) {
            transform: rotate(-45deg) translate(8px, -8px);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 40px;
            left: 0;
            background: #0c0a10; /* 深黑背景 */
            border: 1px solid rgba(192, 162, 61, 0.2); /* 金色边框 */
            border-radius: 6px;
            padding: 8px 0;
            box-shadow: 0 4px 20px rgba(192, 162, 61, 0.1); /* 金色阴影 */
            backdrop-filter: blur(8px); /* 毛玻璃效果 */
        }

        .dropdown-menu.active {
            display: block;
            animation: slideDown 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 24px;
            text-decoration: none;
            color: #c0a23d; /* 主金色 */
            font-size: 0.95rem;
            transition: all 0.25s ease;
            position: relative;
        }

        .dropdown-menu a:hover {
            background: rgba(192, 162, 61, 0.1); /* 淡金背景 */
            color: #f4e3b2; /* 亮金色 */
            padding-left: 28px;
            text-shadow: 0 0 8px rgba(244, 227, 178, 0.3);
        }

        .dropdown-menu a:hover::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background: #f4e3b2;
            border-radius: 50%;
        }

        @keyframes slideDown {
            from { 
                opacity: 0; 
                transform: translateY(-15px) rotateX(-15deg);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) rotateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="menu-container">
        <div class="menu-icon" onclick="toggleMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <nav class="dropdown-menu">
            <a href="../Main Page/main_page.php">Home</a>
            <a href="../Order_Management/index.php">Services</a>
            <a href="../More/Contact.php">Contact</a>
            <a href="../MOre/notifications/index.php">Notifications</a>
        </nav>
    </div>

    <script>
        function toggleMenu() {
            const icon = document.querySelector('.menu-icon');
            const menu = document.querySelector('.dropdown-menu');
            
            icon.classList.toggle('active');
            menu.classList.toggle('active');
        }
    </script>
</body>
</html>


<!-- footer -->

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
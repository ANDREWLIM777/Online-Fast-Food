<?php
include '../auth.php';
include '../Admin_Account/db.php';

/*  获取当前用户信息 */
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM admin WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

/* 处理表单提交更新 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $age = $_POST['age'];
    $phone = $_POST['phone'];

    // 上传照片处理
    if ($_FILES['photo']['name']) {
        $photo_name = time() . '_' . basename($_FILES['photo']['name']);
        $target = "../Admin_Account/upload/" . $photo_name;
        move_uploaded_file($_FILES['photo']['tmp_name'], $target);

        $update = "UPDATE admin SET age=?, phone=?, photo=? WHERE id=?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("issi", $age, $phone, $photo_name, $user_id);
    } else {
        $update = "UPDATE admin SET age=?, phone=? WHERE id=?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("isi", $age, $phone, $user_id);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Profile updated successfully'); window.location.href='main_page.php';</script>";
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brizo Melaka FAST-FOOD Admin</title>
    
    <!-- 公共样式 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
     :root {
    --gold-dark: #c0a23d;
    --gold-light: #d9c88e;
    --bg-dark: #0c0a10;
    --text-light: #eee;
    --box-glow: rgba(255, 215, 0, 0.1);
}

body {
    margin: 0;
    padding: 130px 0 70px;
    font-family: 'Roboto', sans-serif;
    background: var(--bg-dark);
    color: var(--text-light);
}

        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap');

/* 黄金比例艺术标题 */
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
    font-size: 2.1rem;/* 中间尺寸 */
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

/* 双装饰线动画 */
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

/* 动态光晕背景 */
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

/* 微光粒子 */
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


.profile-container {
    position: fixed;
    top: 25px;
    right: 30px;
    z-index: 1001;
}

.profile-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(145deg,rgb(0, 0, 0),rgb(48, 46, 41));
    border: 2px solid rgb(206, 176, 42);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color:rgb(206, 176, 42);
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(36, 35, 35, 0.15);
    transition: all 0.2s ease-in-out;
}

.profile-icon:hover {
    transform: scale(1.08);
}

.profile-dropdown {
    display: none;
    position: absolute;
    top: 50px;
    right: 0;
    background: #0c0a10; /* 深黑底色 */
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(192, 162, 61, 0.15); /* 金色阴影 */
    overflow: hidden;
    min-width: 180px;
    font-family: 'Roboto', sans-serif;
    border: 1px solid #c0a23d55; /* 半透明金色边框 */
    backdrop-filter: blur(8px); /* 毛玻璃效果 */
}

.profile-dropdown a {
    display: block;
    padding: 12px 16px;
    color: #c0a23d; /* 主金色 */
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.2s;
    border-bottom: 1px solid #1a1a1a; /* 分隔线 */
}

.profile-dropdown a {
    display: block;
    padding: 12px 16px;
    color: #c0a23d; /* 主金色 */
    text-decoration: none;
    font-size: 0.95rem;
    transition: all 0.2s;
    border-bottom: 1px solid #1a1a1a; /* 分隔线 */
}

.profile-dropdown a:last-child {
    border-bottom: none;
}

/* 悬停效果 */
.profile-dropdown a:hover {
    background: #c0a23d15; /* 浅金底色 */
    color: #f4e3b2; /* 亮金色 */
    padding-left: 20px; /* 动态缩进 */
}

/* 当前选中状态 */
.profile-dropdown a.active {
    background: linear-gradient(90deg, #c0a23d20, transparent);
    border-left: 3px solid #c0a23d;
}

.profile-header {
    text-align: left;
    padding: 12px 16px 8px;
    background-color: transparent;
}

.profile-name {
    font-weight: bold;
    color: #f4e3b2;
    font-size: 1rem;
    margin-bottom: 2px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.profile-role {
    font-size: 0.85rem;
    color: #bba350;
    text-transform: lowercase;
    opacity: 0.9;
}

/* 分隔线 */
.profile-dropdown hr {
    border: none;
    border-top: 1px solid #1a1a1a;
    margin: 5px 0 5px;
}



        /* 主要内容区域 */
        .dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    padding: 30px;
}

.card {
    background: rgba(32, 32, 32, 0.85);
    border: 1px solid #c0a23d33;
    border-radius: 20px;
    padding: 36px;
    box-shadow: 0 0 12px var(--box-glow);
    transition: all 0.3s ease;
    cursor: pointer;
    backdrop-filter: blur(6px);
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 0 18px #c0a23d88;
}

.card-icon {
    font-size: 2.8em;
    color: var(--gold-dark);
    margin-bottom: 18px;
}

.card h3 {
    margin: 0 0 10px;
    font-size: 1.6em;
    color: var(--gold-light);
    font-family: 'Playfair Display', serif;
}

.card p {
    font-size: 1em;
    color: #bfbfbf;
    line-height: 1.5;
}

   

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.6em;
        }

/* 响应式布局调整 */
.dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    padding: 20px;
}

/* 大屏幕适配 3x2 */
@media (min-width: 769px) {
    .dashboard {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* 小屏幕适配 1 列 */
@media (max-width: 768px) {
    .dashboard {
        grid-template-columns: 1fr;
    }
}
    </style>
</head>
<body>
    <!-- 导入菜单图标 -->
    <!--#include virtual="menu_icon.html" -->

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
            <div class="sub-title">Admin Page</div>
        </div>
    </div>



    <!-- 主内容 -->
    <main class="dashboard">
        <div class="card" onclick="location.href='../Manage_Menu_Item/index.php'">
            <i class="fas fa-hamburger card-icon"></i>
            <h3>Manage Menu Items</h3>
            <p>Add, edit or remove menu items and set pricing</p>
        </div>

        <div class="card" onclick="location.href='transactions.html'">
            <i class="fas fa-receipt card-icon"></i>
            <h3>Transaction History</h3>
            <p>View and analyze daily sales reports</p>
        </div>

        <div class="card" onclick="location.href='orders.html'">
            <i class="fas fa-clipboard-list card-icon"></i>
            <h3>Order Management</h3>
            <p>Real-time order tracking and processing</p>
        </div>

        <div class="card" onclick="location.href='../Admin_Account/register.php'">
              <i class="fas fa-id-card card-icon"></i>
              <h3>New Admin Registration</h3>
              <p>Create and authorize new administrator accounts</p>
        </div>

        <div class="card" onclick="location.href='../Manage_Customer/index.php'">
        <i class="fas fa-user-shield card-icon"></i>
        <h3>Customer Account Control Panel</h3>
        <p>Manage customer profile, security settings, and account details</p>
        </div>

        <div class="card" onclick="location.href='../Manage_Account/index.php'">
            <i class="fas fa-users-cog card-icon"></i>
            <h3>Admin Account Control Panel</h3>
<p>Manage existing administrator profiles and access privileges</p>
        </div>
    </main>


    <script>
        
function toggleProfile() {
    const dropdown = document.getElementById("profileDropdown");
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

window.onclick = function(event) {
    const dropdown = document.getElementById("profileDropdown");
    if (!event.target.closest('.profile-container')) {
        dropdown.style.display = "none";
    }
};

        // 动态卡片交互
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseover', () => {
                card.style.boxShadow = '0 8px 15px rgba(0,0,0,0.2)';
            });
            
            card.addEventListener('mouseout', () => {
                card.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            });
        });

    </script>
</body>
</html>

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
            <a href="../Manage_Account/index.php">Admin</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
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
<div class="nav-item other-item" style="--active-color: #27ae60;">
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
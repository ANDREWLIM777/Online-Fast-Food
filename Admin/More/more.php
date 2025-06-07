<?php
include '../auth.php';
include '../Admin_Account/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>More - Brizo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --gold: #C4AA6D;
      --dark-bg: #0F0E15;
      --card-bg: #1A191F;
    }

    body {
      background: var(--dark-bg);
      font-family: 'Montserrat', sans-serif;
      color: #F5F4F0;
      margin: 0;
      padding: 0;
      line-height: 1.6;
    }

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


.more-container {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 0 2rem; 
}

.more-container-top {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 100px 2rem 0 2rem; 
}

.more-container-down {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 0 2rem 40px; 
}

    .more-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.8rem 2.5rem;
      margin: 1.5rem 0;
      background: var(--card-bg);
      border: 1px solid rgba(196, 170, 109, 0.397);
      border-radius: 12px;
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
      text-decoration: none !important;
    }

    .more-item::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(45deg,
          transparent,
          rgba(163, 141, 91, 0.05),
          transparent);
      transform: rotate(45deg);
      transition: 0.6s;
    }

    .more-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 24px rgba(0,0,0,0.3),
                  0 0 0 1px rgba(196, 170, 109, 0.3);
    }

    .more-item:hover::before {
      top: 50%;
      left: 50%;
    }

    .more-item span {
      font-size: 1.8rem;
      color: var(--gold);
      font-weight: 500;
      letter-spacing: 0.5px;
    }

    .more-item i {
      color: var(--gold);
      font-size: 1.8rem;
      transition: transform 0.3s ease;
    }

    .more-item:hover i {
      transform: translateX(4px);
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
    background: linear-gradient(145deg, #f5e8d0, #e6d8b3);
    border: 2px solid #c8a951;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #725c1d;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
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
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    min-width: 180px;
    font-family: 'Roboto', sans-serif;
    border: 1px solid #eee;
}

.profile-dropdown a {
    display: block;
    padding: 12px 16px;
    color: #333;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.2s;
}

.profile-dropdown a:hover {
    background: #f8f8f8;
}

/*black version*/

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
    background: #0c0a10; 
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(192, 162, 61, 0.15); 
    overflow: hidden;
    min-width: 180px;
    font-family: 'Roboto', sans-serif;
    border: 1px solid #c0a23d55; 
    backdrop-filter: blur(8px); 
}

.profile-dropdown a {
    display: block;
    padding: 12px 16px;
    color: #c0a23d; 
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.2s;
    border-bottom: 1px solid #1a1a1a; 
}

.profile-dropdown a {
    display: block;
    padding: 12px 16px;
    color: #c0a23d; 
    text-decoration: none;
    font-size: 0.95rem;
    transition: all 0.2s;
    border-bottom: 1px solid #1a1a1a; 
}

.profile-dropdown a:last-child {
    border-bottom: none;
}

.profile-dropdown a:hover {
    background: #c0a23d15; 
    color: #f4e3b2; 
    padding-left: 20px; 
}

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

.profile-dropdown hr {
    border: none;
    border-top: 1px solid #1a1a1a;
    margin: 5px 0 5px;
}

    @media (max-width: 768px) {
      header {
        flex-direction: column;
        gap: 1.5rem;
        padding: 1.5rem;
      }

      .more-item {
        padding: 1.5rem;
      }

      .back-btn {
        left: 1.2rem;
        top: 1.5rem;
        width: 42px;
        height: 42px;
    }
    .back-btn i {
        font-size: 1.4rem;
    }
    }
  </style>
</head>
<body>

    <div class="header">
        <div class="title-group">
            <div class="main-title">BRIZO MELAKA</div>
            <div class="sub-title">More</div>
        </div>
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
    </div>

    <main class="more-container-top">
  <a href="my_acc.php" class="more-item">
    <span>My Account</span>
    <i class="fas fa-angle-right"></i>
  </a>
</main>

<main class="more-container">
  <a href="change_pass.php" class="more-item">
    <span>Password & Security</span>
    <i class="fas fa-angle-right"></i>
  </a>
</main>

<main class="more-container">
  <a href="../Admin_Account/register.php" class="more-item">
    <span>New Admin Registration</span>
    <i class="fas fa-angle-right"></i>
  </a>
</main>

<main class="more-container">
  <a href="notifications/index.php" class="more-item">
    <span>Announcement</span>
    <i class="fas fa-angle-right"></i>
  </a>
</main>

<main class="more-container-down">
  <a href="Contact.php" class="more-item">
    <span>Contact</span>
    <i class="fas fa-angle-right"></i>
  </a>
</main>


<script>
function toggleProfile() {
    const dropdown = document.getElementById("profileDropdown");
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

window.onclick = function(event) {
    const profileContainer = document.querySelector('.profile-container');
    const dropdown = document.getElementById("profileDropdown");
    
    if (!profileContainer.contains(event.target)) {
        dropdown.style.display = "none";
    }
};
</script>

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
            background: #eace7c; 
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
            background: #0c0a10; 
            border: 1px solid rgba(192, 162, 61, 0.2);
            border-radius: 6px;
            padding: 8px 0;
            box-shadow: 0 4px 20px rgba(192, 162, 61, 0.1); 
            backdrop-filter: blur(8px); 
        }

        .dropdown-menu.active {
            display: block;
            animation: slideDown 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 24px;
            text-decoration: none;
            color: #c0a23d;
            font-size: 0.95rem;
            transition: all 0.25s ease;
            position: relative;
        }

        .dropdown-menu a:hover {
            background: rgba(192, 162, 61, 0.1);
            color: #f4e3b2;
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
            <a href="../MOre/notifications/index.php">Announcement</a>
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

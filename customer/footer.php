<!-- footer -->

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
            background: #fffbed;
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
            stroke: #636e72;
            transition: all 0.3s ease;
        }

        .nav-label {
            font-family: 'Segoe UI', sans-serif;
            font-size: 12px;
            color: #636e72;
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
            background: #fafaf8db;
            transform: translateY(-4px);
        }

        .bz-text {
            font-size: 35px;
            font-weight: bold;
            fill: #ff6b6b;
            transition: all 0.3s ease;
        }

        .bz-item.active .bz-text {
            font-size: 18px;
            fill: var(--active-color);
        }

        .bz-item:hover .bz-text {
            font-size: 18px;
            fill: var(--active-color);
        }
    </style>
</head>
<body>

<!-- Footer Navigation -->
<nav class="footer-nav">

    <!-- Menu -->
    <div class="nav-item bz-item" style="--active-color: #ff6b6b;" data-link="../menu/menu.php">
        <svg viewBox="0 0 50 24">
            <text x="5" y="18" class="bz-text">Bz</text>
        </svg>
        <span class="nav-label">Menu</span>
    </div>

    <!-- All Staff -->
    <div class="nav-item other-item" style="--active-color: #ff9f43;" data-link="../Manage_Account/index.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <span class="nav-label">ALL Staff</span>
    </div>

    <!-- View Cart -->
    <div class="nav-item other-item" style="--active-color: #27ae60;" data-link="../menu/menu.php/cart/cart.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M6 6h15l-1.5 9h-13z"></path>
            <circle cx="9" cy="20" r="1.5"></circle>
            <circle cx="18" cy="20" r="1.5"></circle>
        </svg>
        <span class="nav-label">View Cart</span>
    </div>

 <!-- Profile -->
<div class="nav-item other-item" style="--active-color: #3498db;" data-link="../manage_account/profile.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="12" cy="7" r="4" />
        <path d="M5.5 21a7.5 7.5 0 0 1 13 0" />
    </svg>
    <span class="nav-label">Profile</span>
</div>


    <!-- More -->
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
        const ACTIVE_KEY = 'activeNav';

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
            navItems.forEach(item => item.classList.remove('active'));
            clickedItem.classList.add('active');

            if (bzText) {
                bzText.textContent = (clickedItem === bzItem) ? 'Brizo' : 'Bz';
            }

            const label = clickedItem.querySelector('.nav-label')?.textContent.trim();
            if (label) localStorage.setItem(ACTIVE_KEY, label);

            const targetLink = clickedItem.getAttribute('data-link');
            if (targetLink) window.location.href = targetLink;
        }

        if (bzItem && bzText) {
            bzItem.addEventListener('mouseenter', () => { bzText.textContent = 'Brizo'; });
            bzItem.addEventListener('mouseleave', () => {
                if (!bzItem.classList.contains('active')) bzText.textContent = 'Bz';
            });
        }

        navItems.forEach(item => {
            item.addEventListener('click', handleNavClick);
            item.addEventListener('touchstart', handleNavClick, { passive: true });
        });
    });
</script>

</body>
</html>

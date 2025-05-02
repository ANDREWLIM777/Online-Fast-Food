<!-- Menu Icon -->
<div class="menu-container">
    <div class="menu-icon" onclick="toggleMenu()" title="Menu">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <nav class="dropdown-menu">
        <a href="/Online-Fast-Food/customer/menu/menu.php">Home</a>
        <a href="../manage_account/profile.php">Profile</a>
        <a href="/Online-Fast-Food/customer/payment_history/payment_history.php">Payment History</a>
        <a href="/Online-Fast-Food/customer/logout.php" onclick="return confirm('Are you sure you want to log out?')">Log Out</a>
    </nav>
</div>

<style>
.menu-container {
    position: fixed;
    top: 1px;
    left: 28px;
    z-index: 1000;
}

.menu-icon {
    cursor: pointer;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #ff6b6b; /* Brizo red */
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s ease;
}

.menu-icon span {
    position: absolute;
    height: 3px;
    width: 24px;
    background: #fff;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.menu-icon span:nth-child(1) { top: 14px; }
.menu-icon span:nth-child(2) { top: 22px; }
.menu-icon span:nth-child(3) { top: 30px; }

.menu-icon.active span:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

.menu-icon.active span:nth-child(2) {
    opacity: 0;
}

.menu-icon.active span:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

.dropdown-menu {
    display: none;
    position: absolute;
    top: 69px;
    left: 0;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    border-radius: 12px;
    padding: 10px 0;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
}

.dropdown-menu.active {
    display: block;
    animation: slideDown 0.3s ease;
}

.dropdown-menu a {
    display: block;
    padding: 13px 23px;
    text-decoration: none;
    color: #000;
    font-weight: 500;
    white-space: nowrap;
    transition: background 0.3s ease;
}

.dropdown-menu a:hover {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(15px);
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
function toggleMenu() {
    const icon = document.querySelector('.menu-icon');
    const menu = document.querySelector('.dropdown-menu');
    
    icon.classList.toggle('active');
    menu.classList.toggle('active');
}
</script>

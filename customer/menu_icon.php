<!-- Menu Icon -->
<div class="menu-container">
    <div class="menu-icon" onclick="toggleMenu()" title="Menu Icon">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <nav class="dropdown-menu">
        <a href="/Online-Fast-Food/customer/menu/menu.php">Home</a>
        <a href="../manage_account/profile.php">Profile</a>
                <a href="/Online-Fast-Food/customer/orders/orders.php">My Orders</a>
        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/payment_history.php">Payment History</a>
        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/feedback.php">Feedback</a>

        <?php if (isset($_SESSION['customer_id'])): ?>
        <a href="#" class="btn-logout-animated">Log out</a>
        <?php else: ?>
        <a href="/Online-Fast-Food/customer/login.php" class="btn-login">Login</a>
        <?php endif; ?>
    </nav>
</div>

<!-- 🔒 Custom Logout Modal -->
<div id="logoutModal" class="logout-modal hidden">
  <div class="logout-box">
    <p>Are you sure you want to log out?</p>
    <div class="logout-actions">
      <button id="confirmLogout">Yes, log out</button>
      <button id="cancelLogout">Cancel</button>
    </div>
  </div>
</div>


    <style>
    .menu-container {
        position: fixed;
        top: 18px;
        left: 24px;
        z-index: 1000;
    }

    .menu-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #ff4757;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
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

    .menu-icon:hover {
  background-color: #b92f2f;
}

    .menu-icon span:nth-child(1) { top: 16px; }
    .menu-icon span:nth-child(2) { top: 24px; }
    .menu-icon span:nth-child(3) { top: 32px; }

    .menu-icon.active span:nth-child(1) {
        transform: rotate(45deg) translate(5px, 5px);
    }

    .menu-icon.active span:nth-child(2) {
        opacity: 0;
    }

    .menu-icon.active span:nth-child(3) {
        transform: rotate(-45deg) translate(5px, -5px);
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        top: 62px;
        left: 0;
        background: rgba(255, 250, 250, 0.63);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        padding: 8px 0;
        border: 1px solid rgba(255, 255, 255, 0.34);
        min-width: 180px;
        transition: all 0.3s ease;
    }

    .dropdown-menu.active {
        display: block;
        animation: slideDown 0.3s ease;
    }

    .dropdown-menu a {
        display: block;
        padding: 12px 20px;
        text-decoration: none;
        color: #222;
        font-weight: 500;
        transition: background 0.3s ease;
    }

    .dropdown-menu a:hover {
        background: rgba(255, 157, 157, 0.55);
    }


    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .logout-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    transition: opacity 0.3s ease;
    }

    .logout-modal.hidden {
    opacity: 0;
    pointer-events: none;
    }

    .logout-box {
    background: #fff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    animation: slideUp 0.4s ease forwards;
    max-width: 320px;
    width: 100%;
    text-align: center;
    font-family: 'Lexend', sans-serif;
    }

    .logout-actions {
    display: flex;
    justify-content: space-around;
    margin-top: 1.5rem;
    }

    .logout-actions button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    }

    #confirmLogout {
    background-color: #d63031;
    color: white;
    }

    #cancelLogout {
    background-color: #b2bec3;
    color: #2d3436;
    }

    @keyframes slideUp {
    from {
        transform: translateY(60px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
    }

    </style>

    <script>

    document.addEventListener("DOMContentLoaded", () => {
    const logoutBtn = document.querySelector(".btn-logout-animated");
    const modal = document.getElementById("logoutModal");
    const confirmBtn = document.getElementById("confirmLogout");
    const cancelBtn = document.getElementById("cancelLogout");

    logoutBtn?.addEventListener("click", e => {
        e.preventDefault();
        modal.classList.remove("hidden");
    });

    confirmBtn?.addEventListener("click", () => {
        window.location.href = "/Online-Fast-Food/customer/logout.php";
    });

    cancelBtn?.addEventListener("click", () => {
        modal.classList.add("hidden");
    });
    });

    function toggleMenu() {
        const icon = document.querySelector('.menu-icon');
        const menu = document.querySelector('.dropdown-menu');
        
        icon.classList.toggle('active');
        menu.classList.toggle('active');
    }



    </script>

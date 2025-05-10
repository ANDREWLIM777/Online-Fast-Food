<!-- ✅ Footer Navigation + Sidebar (Clean and Fully Functional) -->

<!-- 🦶 Footer Navigation -->
<nav class="footer-nav">

  <div class="nav-item" style="--active-color:rgb(4, 4, 4);" data-link="/Online-Fast-Food/customer/home/home.php">
  <svg viewBox="0 0 50 24"><text x="5" y="18" class="bz-text">Bz</text></svg>
  <span class="nav-label">Home</span>
</div>

<div class="nav-item bz-item" style="--active-color: #ff6b6b;" data-link="/Online-Fast-Food/customer/menu/menu.php">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <!-- Top Bun (soft arc with sesame seeds) -->
    <path d="M3 9c0-4 18-4 18 0" />
    <circle cx="8" cy="7.5" r="0.4" />
    <circle cx="12" cy="7" r="0.4" />
    <circle cx="16" cy="7.5" r="0.4" />
    
    <!-- Lettuce Wave -->
    <path d="M4 11c1 0 1-1 2-1s1 1 2 1 1-1 2-1 1 1 2 1 1-1 2-1 1 1 2 1 1-1 2-1 1 1 2 1" />
    
<!-- Patty: double-line for thickness and realism -->
<path d="M4 13h16" stroke-width="2.2" stroke-linecap="round" />
<path d="M4 14.2h16" stroke-width="1.5" stroke-linecap="round" opacity="0.85" />

<!-- Bottom Bun (matching top bun) -->
<path d="M3 16a9 4 0 0 0 18 0H3z" />

  </svg>
     <span class="nav-label">Menu</span>
  </div>

  <div class="nav-item" style="--active-color: #27ae60;" data-link="/Online-Fast-Food/customer/menu/cart/cart.php">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
<circle cx="9" cy="21" r="1.5"/>
<circle cx="20" cy="21" r="1.5"/>
<path d="M1 1h4l2.5 13h11l2-8H6"/>
</svg>    <span class="nav-label">Cart</span>
  </div>

  <div class="nav-item" style="--active-color: #3498db;" data-link="/Online-Fast-Food/customer/Manage_Account/profile.php">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>    <span class="nav-label">Profile</span>
  </div>

  <div class="nav-item" id="moreTrigger" style="--active-color: #8e44ad;">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="12" cy="5" r="1"></circle>
                <circle cx="12" cy="19" r="1"></circle>
            </svg>    <span class="nav-label">More</span>
  </div>
</nav>

<!-- 🔥 More Sidebar + Backdrop -->
<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<div class="more-sidebar" id="moreSidebar">
  <div class="more-header">
    <span>More Options</span>
    <button id="closeSidebar">&times;</button>
  </div>
  <ul class="more-links">
    <li><a href="/Online-Fast-Food/customer/home/home.php">Home</a></li>
<!-- Add under login form -->
<a href="/Online-Fast-Food/customer/forgot_password/forgot_password.php" class="forgot-link">Forgot your password?</a>
    <li><a href="/Online-Fast-Food/payment/保存/payment_history.php">Payment History</a></li>
    <li><a href="/Online-Fast-Food/customer/notification/notification.php">News</a></li>
    <li><a href="/Online-Fast-Food/customer/customer_notification/customer_notification.php">Notification</a></li>
    <li><a href="/Online-Fast-Food/customer/brizo/about_brizo.php">About Us</a></li>
    <li><a href="/Online-Fast-Food/Admin/Admin_Account/login.php">Login as Admin</a></li>
  </ul>
</div>

<style>
  .footer-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 70px;
    background:rgb(255, 248, 221);
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

  .nav-item.active svg,
  .nav-item.active .nav-label {
    stroke: var(--active-color);
    color: var(--active-color);
  }

  .bz-text {
    font-size: 35px;
    font-weight: bold;
    fill: #ff6b6b;
    transition: all 0.3s ease;
  }

  .bz-item.active .bz-text,
  .bz-item:hover .bz-text {
    font-size: 18px;
    fill: var(--active-color);
  }

  /* 🔥 Sidebar */
  .sidebar-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 9998;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .sidebar-backdrop.show {
    display: block;
    opacity: 1;
  }

  .more-sidebar {
    position: fixed;
    top: 0;
    right: -100%;
    width: 300px;
    height: 100%;
    background: #fffdf7;
    box-shadow: -4px 0 15px rgba(0, 0, 0, 0.1);
    transition: right 0.35s ease;
    z-index: 9999;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
  }

  .more-sidebar.open {
    right: 0;
  }

  .more-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 1rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5rem;
  }

  #closeSidebar {
    font-size: 1.6rem;
    background: none;
    border: none;
    color: #d62828;
    cursor: pointer;
  }

  .more-links {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .more-links li {
    margin: 12px 0;
  }

  .more-links a {
    color: #444;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s ease;
  }

  .more-links a:hover {
    color: #d62828;
  }

  @media (max-width: 400px) {
    .more-sidebar {
      width: 100%;
    }
  }

  body {
  padding-bottom: 90px;
  box-sizing: border-box;
}

</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const navItems = document.querySelectorAll('.nav-item');
  const moreBtn = document.getElementById('moreTrigger');
  const sidebar = document.getElementById('moreSidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const closeBtn = document.getElementById('closeSidebar');
  const bzItem = document.querySelector('.bz-item');
  const bzText = bzItem?.querySelector('.bz-text');
  const ACTIVE_KEY = 'activeNav';

  // Highlight nav
  const saved = localStorage.getItem(ACTIVE_KEY);
  if (saved) {
    navItems.forEach(item => {
      const label = item.querySelector('.nav-label')?.textContent.trim();
      if (label === saved) {
        item.classList.add('active');
        if (bzText) bzText.textContent = (item === bzItem) ? 'Brizo' : 'Bz';
      }
    });
  }

  navItems.forEach(item => {
    if (item !== moreBtn) {
      item.addEventListener('click', e => {
        navItems.forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        const label = item.querySelector('.nav-label')?.textContent.trim();
        if (label) localStorage.setItem(ACTIVE_KEY, label);
        const link = item.getAttribute('data-link');
        if (link) window.location.href = link;
      });
    }
  });

  bzItem?.addEventListener('mouseenter', () => { bzText.textContent = 'Brizo'; });
  bzItem?.addEventListener('mouseleave', () => {
    if (!bzItem.classList.contains('active')) bzText.textContent = 'Bz';
  });

  // Sidebar control
  moreBtn.addEventListener('click', () => {
    sidebar.classList.add('open');
    backdrop.classList.add('show');
  });

  closeBtn.addEventListener('click', () => {
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
  });

  backdrop.addEventListener('click', () => {
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
  });
});
</script>
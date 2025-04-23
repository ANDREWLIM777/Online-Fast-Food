<?php
require 'db_conn.php';
session_start();


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #121212;
      color: #eee;
      margin: 0;
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
      text-align: center;
      font-size: 2.5rem;
      color: #c0a23d;
    }

    .tabs {
      display: flex;
      justify-content: center;
      margin-bottom: 2rem;
    }

    .tab-btn {
      background: #252525;
      color: #e8d48b;
      border: none;
      padding: 10px 30px;
      font-size: 1rem;
      cursor: pointer;
      border-bottom: 3px solid transparent;
    }

    .tab-btn.active {
      border-bottom: 3px solid gold;
      font-weight: bold;
    }

    .tab-page { display: none; }
    .tab-page.active { display: block; }

    .order-card, .refund-card {
  background: #fffbe6;
  color: #1c1c1c;
  padding: 2rem;
  border-radius: 14px;
  margin: 2rem auto;
  max-width: 800px;
  box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
  border: 2px solid #c0a23d;
}

    .order-card h3 { margin-top: 0; color: #000; }

    .actions {
      display: flex;
      justify-content: space-between;
      margin-top: 1rem;
    }

    .btn-approve {
      flex: 3;
      background: #4CAF50;
      color: white;
      border: none;
      padding: 10px;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1rem;
    }

    .btn-refund {
      flex: 1;
      background: #f44336;
      color: white;
      border: none;
      padding: 10px;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1rem;
    }

    .btn-approve:hover { background: #45a049; }
    .btn-refund:hover { background: #e53935; }

    .back-btn {
      display: inline-block;
      background: linear-gradient(to right, #c0a23d, #e8d48b);
      color: #000;
      font-weight: bold;
      padding: 10px 20px;
      border-radius: 10px;
      text-decoration: none;
      transition: 0.2s ease;
      margin-bottom: 2rem;
    }

    .back-btn:hover {
      background: #e8d48b;
      box-shadow: 0 0 10px #e8d48b;
    }

    /* Modal */
    .modal {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.6);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }

    .modal-content {
      background: #1c1c1c;
      padding: 2rem;
      border-radius: 10px;
      width: 400px;
      text-align: center;
      color: #fff;
      border: 2px solid #c0a23d;
    }

    .modal-actions {
      margin-top: 20px;
      display: flex;
      justify-content: space-around;
    }

    .modal-actions .cancel {
      background: #444;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
    }

    .modal-actions .confirm {
      background: #ff4d4d;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
    }
  </style>
</head>
<body>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
  <a href="../Main Page/main_page.php" class="back-btn">
    <i class="fas fa-house"></i> Back To Main Page
  </a>
  <h1 style="flex: 1; text-align: center; color: #c0a23d; font-size: 2.5rem;">📦 Order Management</h1>
  <div style="width: 230px;"></div> 
</div>

<div class="tabs">
  <button class="tab-btn active" onclick="showTab(event, 'approve')">Order Approve</button>
  <button class="tab-btn" onclick="showTab(event, 'refund')">Refund Requests</button>
</div>

<!-- Order Approval -->
<div id="approve" class="tab-page active">
  <?php
  $orders = $pdo->query("SELECT * FROM orders WHERE status = 'pending' ORDER BY created_at ASC")->fetchAll();
  foreach ($orders as $order):
    $items = json_decode($order['items'], true);
  ?>
  <div class="order-card">
    <h3>🧾 Order: <?= htmlspecialchars($order['order_code']) ?></h3>
    <ul>
      <?php foreach ($items as $i): 
        $item = $pdo->prepare("SELECT item_name FROM menu_items WHERE id = ?");
        $item->execute([$i['item_id']]);
        $item_name = $item->fetchColumn();
      ?>
        <li><?= $item_name ?> x <?= $i['quantity'] ?></li>
      <?php endforeach; ?>
    </ul>
    <strong>Total: RM <?= number_format($order['total'], 2) ?></strong>
    <div class="actions">
      <form action="approve_order.php" method="post">
        <input type="hidden" name="id" value="<?= $order['id'] ?>">
        <button class="btn-approve"><i class="fas fa-check"></i> Complete</button>
      </form>
      <form onsubmit="openRefundModal(this, true); return false;" method="post" action="refund_order.php">
        <input type="hidden" name="id" value="<?= $order['id'] ?>">
        <button class="btn-refund"><i class="fas fa-times"></i></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Refund Request -->
<div id="refund" class="tab-page">
  <?php
  $refunds = $pdo->query("SELECT * FROM refund_requests WHERE status = 'pending' ORDER BY date DESC")->fetchAll();
  foreach ($refunds as $r):
  ?>
  <div class="refund-card">
    <h3>Refund for Order: <?= htmlspecialchars($r['order_id']) ?></h3>
    <p><strong>Reason:</strong> <?= htmlspecialchars($r['reason']) ?></p>
    <p><?= nl2br(htmlspecialchars($r['details'])) ?></p>
    <div class="actions">
      <form action="handle_refund.php" method="post">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn-approve" name="action" value="approve"><i class="fas fa-check"></i> Approve</button>
      </form>
      <form onsubmit="openDropdownModal(this); return false;" method="post" action="handle_refund.php">
  <input type="hidden" name="id" value="<?= $r['id'] ?>">
  <input type="hidden" name="action" value="reject">
  <button class="btn-refund"><i class="fas fa-times"></i></button>
</form>


    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Modal -->

<div id="confirmModal" class="modal">
  <div class="modal-content">
    <h3>⚠️ Confirm Refund</h3>
    <p id="confirmText">Please enter refund reason:</p>
    <textarea id="confirmReason" placeholder="Enter reason here..." style="width: 100%; margin-top: 10px;"></textarea>
    <div class="modal-actions">
      <button onclick="closeModal()" class="cancel">Cancel</button>
      <button onclick="submitRefund()" class="confirm">Yes, Refund</button>
    </div>
  </div>
</div>

<div id="confirmDropdownModal" class="modal">
  <div class="modal-content">
    <h3>⚠️ Reject Refund Request</h3>
    <select id="confirmReasonDropdown" style="width: 100%; padding: 10px; font-size: 1rem;">
      <option disabled selected>Select a reason...</option>
      <option value="Item already consumed">Item already consumed</option>
      <option value="Order completed">Order completed</option>
      <option value="Invalid refund request">Invalid refund request</option>
      <option value="Outside refund window">Outside refund window</option>
    </select>
    <div class="modal-actions">
      <button onclick="closeDropdownModal()" class="cancel">Cancel</button>
      <button onclick="submitRefundDropdown()" class="confirm">Confirm</button>
    </div>
  </div>
</div>


<script>



function showTab(e, id) {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-page').forEach(page => page.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  e.target.classList.add('active');
}

let currentForm = null;
function openRefundModal(form, requireReason = false) {
  currentForm = form;
  document.getElementById("confirmModal").style.display = "flex";
  document.getElementById("confirmReason").style.display = requireReason ? "block" : "none";
}

function submitRefund() {
  const reasonField = document.getElementById("confirmReason");
  const reason = reasonField.value.trim();

  if (reason === "") {
    reasonField.style.border = "2px solid red";
    reasonField.placeholder = "⚠ Please provide a reason!";
    reasonField.focus();
    return;
  }

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'note';
  input.value = reason;
  currentForm.appendChild(input);
  currentForm.submit();
}

function submitRefundDropdown() {
  const select = document.getElementById("confirmReasonDropdown");
  const reason = select.value;

  if (!reason || reason === "Select a reason...") {
    select.style.border = "2px solid red";
    return;
  }

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'reason';
  input.value = reason;
  currentForm.appendChild(input);
  currentForm.submit();
}


function closeModal() {
  document.getElementById("confirmModal").style.display = "none";
  currentForm = null;
}

function openRefundModal(form, requireReason = false) {
  currentForm = form;
  document.getElementById("confirmModal").style.display = "flex";
}

function openDropdownModal(form) {
  currentForm = form;
  document.getElementById("confirmDropdownModal").style.display = "flex";
}

function closeDropdownModal() {
  document.getElementById("confirmDropdownModal").style.display = "none";
  currentForm = null;
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

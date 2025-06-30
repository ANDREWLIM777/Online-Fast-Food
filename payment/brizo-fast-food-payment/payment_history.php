<?php
ob_start();
session_start();
require '../db_connect.php';

// Enable debug mode
$debug = true;
$logFile = 'payment_history_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    $logMessage("Session expired for customer_id: " . ($_SESSION['customer_id'] ?? 'unknown'));
    header("Location: /Online-Fast-Food/customer/login.php?message=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Verify database connection
if ($conn->connect_error) {
    $logMessage("Database connection failed: " . $conn->connect_error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database connection failed"));
    exit();
}

// Ensure user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

$customerId = (int)$_SESSION['customer_id'];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$csrf_param = urlencode($csrfToken);

// Validate CSRF token for GET requests
if (isset($_GET['csrf_token']) && $_GET['csrf_token'] !== $csrfToken) {
    $logMessage("Invalid CSRF token for page: " . ($_GET['page'] ?? 'unknown'));
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Invalid CSRF token"));
    exit();
}

// Fetch payment history with pagination
$records_per_page = 10;
$page = max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($page - 1) * $records_per_page;

// Count total records
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM payment_history ph
    INNER JOIN orders o ON ph.order_id = o.order_id
    WHERE ph.customer_id = ?
");
if (!$stmt) {
    $logMessage("Prepare failed for count: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("i", $customerId);
if (!$stmt->execute()) {
    $logMessage("Execute failed for count: " . $stmt->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_records / $records_per_page);

// Fetch payment history with status from orders table
$stmt = $conn->prepare("
    SELECT ph.order_id, ph.date, ph.amount, o.status, ph.method, ph.payment_details, ph.delivery_method, ph.delivery_address
    FROM payment_history ph
    INNER JOIN orders o ON ph.order_id = o.order_id
    WHERE ph.customer_id = ?
    ORDER BY ph.date DESC
    LIMIT ? OFFSET ?
");
if (!$stmt) {
    $logMessage("Prepare failed for payment history: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("iii", $customerId, $records_per_page, $offset);
if (!$stmt->execute()) {
    $logMessage("Execute failed for payment history: " . $stmt->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$payment_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Log page access
$logMessage("Payment history accessed for customer_id: $customerId, page: $page");

// Function to get status color
function getStatusColor($status) {
    // Always return green for "Successful" display
    return 'text-green-600';
}

// Function to format delivery address
function formatDeliveryAddress($address) {
    if (empty($address)) return 'N/A';
    $decoded = json_decode($address, true);
    if ($decoded && is_array($decoded)) {
        $addressParts = [];
        if (!empty($decoded['street_address'])) $addressParts[] = htmlspecialchars($decoded['street_address']);
        if (!empty($decoded['city'])) $addressParts[] = htmlspecialchars($decoded['city']);
        if (!empty($decoded['postal_code'])) $addressParts[] = htmlspecialchars($decoded['postal_code']);
        return implode(', ', $addressParts) ?: 'N/A';
    }
    return htmlspecialchars($address);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Brizo Fast Food Melaka</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .card-hover { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .table-container { overflow-x: auto; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .history-table th, .history-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            text-align: center;
        }
        .history-table th {
            background-color: #f3f4f6;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            width: 12.5%;
        }
        .history-table tr:nth-child(even) { background-color: #f9fafb; }
        .history-table tr:hover { background-color: #f1f5f9; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; border-radius: 10px;
            font-weight: 500; font-size: 14px; color: white; text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            cursor: pointer; border: none;
            white-space: nowrap; /* Prevent text wrapping */
        }
        .btn:hover { transform: scale(1.05); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); opacity: 0.9; }
        .btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.3); }
        .btn:active { transform: scale(1); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); }
        .btn i { margin-right: 6px; font-size: 14px; }
        .btn-primary { background-color: #ff4757; }
        .btn-primary:hover { background-color: #e63946; }
        .btn-orders {
            background-color: #3b82f6;
            width: 150px; /* Adjusted width to fit "Go to Orders" on one line */
        }
        .btn-orders:hover { background-color: #2563eb; }
        .text-primary { color: #ff4757; }
        .text-primary:hover { color: #e63946; }
        .pagination span { background-color: #e5e7eb; color: #374151; padding: 8px 16px; border-radius: 8px; font-weight: 500; }
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .icon { margin-right: 5px; }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="http://localhost/Online-Fast-Food/customer/menu/menu.php" class="text-primary hover:text-primary flex items-center">
                <i class="fas fa-utensils mr-2"></i> Back to Menu
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="card p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Payment History</h2>
            <p class="text-gray-600 mb-4">Customer ID: <?= htmlspecialchars($customerId) ?></p>

            <!-- Actions -->
            <div class="mb-6 flex justify-end">
                <a href="/Online-Fast-Food/customer/orders/orders.php" class="btn btn-orders">
                    <i class="fas fa-list-alt"></i> Go to Orders
                </a>
            </div>

            <?php if (empty($payment_history)): ?>
                <p class="text-gray-600">No payment history found.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="history-table" role="grid" aria-label="Payment history">
                        <thead>
                            <tr>
                                <th scope="col">Order ID</th>
                                <th scope="col">Date</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Status</th>
                                <th scope="col">Payment Method</th>
                                <th scope="col">Delivery Method</th>
                                <th scope="col">Delivery Address</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_history as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['order_id']) ?></td>
                                    <td><?= date('d M Y, H:i:s', strtotime($row['date'] ?? 'now')) ?></td>
                                    <td>RM <?= number_format($row['amount'], 2) ?></td>
                                    <td class="<?= getStatusColor($row['status']) ?>">
                                        <i class="fas fa-circle icon text-xs"></i>
                                        Successful
                                    </td>
                                    <td>
                                        <i class="fas <?= ($row['method'] ?? 'unknown') === 'card' ? 'fa-credit-card' : (($row['method'] ?? 'unknown') === 'online_banking' ? 'fa-university' : 'fa-wallet') ?> icon"></i>
                                        <?= htmlspecialchars(ucfirst($row['method'] ?? 'Unknown')) ?> (<?= htmlspecialchars($row['payment_details'] ?? 'N/A') ?>)
                                    </td>
                                    <td>
                                        <i class="fas <?= ($row['delivery_method'] ?? 'pickup') === 'delivery' ? 'fa-truck' : 'fa-store' ?> icon"></i>
                                        <?= htmlspecialchars(ucfirst($row['delivery_method'] ?? 'Pickup')) ?>
                                    </td>
                                    <td><?= formatDeliveryAddress($row['delivery_address'] ?? '') ?></td>
                                    <td>
                                        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/confirmation.php?order_id=<?= urlencode($row['order_id']) ?>&csrf_token=<?= $csrf_param ?>" class="btn btn-primary" aria-label="View order confirmation for order <?= htmlspecialchars($row['order_id']) ?>">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex justify-between items-center">
                        <div>
                            <p class="text-gray-600">
                                Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> records
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <a href="?page=<?= max(1, $page - 1) ?>&csrf_token=<?= $csrf_param ?>" class="btn btn-primary <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?> aria-label="Previous page">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            <span class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">
                                Page <?= $page ?> of <?= $total_pages ?>
                            </span>
                            <a href="?page=<?= min($total_pages, $page + 1) ?>&csrf_token=<?= $csrf_param ?>" class="btn btn-primary <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $page >= $total_pages ? 'aria-disabled="true"' : '' ?> aria-label="Next page">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php
ob_end_flush();
?>

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

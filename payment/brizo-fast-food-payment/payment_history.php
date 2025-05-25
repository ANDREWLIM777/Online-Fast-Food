<?php
ob_start();
session_start();
require '../db_connect.php';

// Debug mode
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
    header("Location: /Online-Fast-Food/login.php?message=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Check database connection
if ($conn->connect_error) {
    $logMessage("Database connection failed: " . $conn->connect_error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database connection failed"));
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: /Online-Fast-Food/login.php");
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

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM payment_history WHERE customer_id = ?");
if (!$stmt) {
    $logMessage("Prepare failed for count: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_records / $records_per_page);

$stmt = $conn->prepare("
    SELECT order_id, date, amount, status, method, payment_details, delivery_method, delivery_address
    FROM payment_history
    WHERE customer_id = ?
    ORDER BY date DESC
    LIMIT ? OFFSET ?
");
if (!$stmt) {
    $logMessage("Prepare failed for payment history: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("iii", $customerId, $records_per_page, $offset);
$stmt->execute();
$payment_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Log access
$logMessage("Payment history accessed for customer_id: $customerId, page: $page");

// Function to get status color
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'completed': return 'text-green-600';
        case 'pending': return 'text-yellow-600';
        case 'failed': return 'text-red-600';
        case 'refunded': return 'text-blue-600';
        default: return 'text-gray-600';
    }
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
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .table-container { overflow-x: auto; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        .history-table tr { transition: background-color 0.3s ease, transform 0.2s ease; }
        .history-table tr:nth-child(even) { background-color: #f9fafb; }
        .history-table tr:hover { background-color: #f1f5f9; transform: scale(1.01); }
        .history-table th, .history-table td { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
        .history-table th { background-color: #f3f4f6; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; border-radius: 8px;
            font-weight: 500; font-size: 14px; color: white; text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.3s ease;
            cursor: pointer; border: none;
        }
        .btn-primary { background-color: #ff4757; }
        .btn-primary:hover { background-color: #e63946; transform: scale(1.05); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); }
        .text-primary { color: #ff4757; }
        .text-primary:hover { color: #e63946; }
        .pagination span { background-color: #e5e7eb; color: #374151; padding: 8px 16px; border-radius: 8px; font-weight: 500; }
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s ease, transform 0.2s ease;
        }
        .card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="/Online-Fast-Food/customer/menu/cart/cart.php" class="text-primary hover:text-primary flex items-center" aria-label="Return to cart page">
                <i class="fas fa-shopping-cart mr-2"></i> Back to Cart
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="card p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Payment History</h2>
            <p class="text-gray-600 mb-4">Your Customer ID: <?= htmlspecialchars($customerId) ?></p>

            <?php if (empty($payment_history)): ?>
                <p class="text-gray-600">No payment history found.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="history-table w-full" role="grid" aria-label="Payment history">
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
                            <?php foreach ($payment_history as $payment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($payment['order_id']) ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($payment['date'])) ?></td>
                                    <td>RM <?= number_format($payment['amount'], 2) ?></td>
                                    <td class="<?= getStatusColor($payment['status']) ?>">
                                        <i class="fas fa-circle mr-1 text-xs"></i>
                                        <?= htmlspecialchars(ucfirst($payment['status'])) ?>
                                    </td>
                                    <td>
                                        <i class="fas <?= $payment['method'] === 'card' ? 'fa-credit-card' : ($payment['method'] === 'online_banking' ? 'fa-university' : 'fa-wallet') ?> mr-1"></i>
                                        <?= htmlspecialchars(ucfirst($payment['method'])) ?> (<?= htmlspecialchars($payment['payment_details']) ?>)
                                    </td>
                                    <td>
                                        <i class="fas <?= $payment['delivery_method'] === 'delivery' ? 'fa-truck' : 'fa-store' ?> mr-1"></i>
                                        <?= htmlspecialchars(ucfirst($payment['delivery_method'])) ?>
                                    </td>
                                    <td><?= formatDeliveryAddress($payment['delivery_address']) ?></td>
                                    <td>
                                        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/confirmation.php?order_id=<?= urlencode($payment['order_id']) ?>&csrf_token=<?= $csrf_param ?>" class="btn btn-primary" aria-label="View order confirmation for order <?= htmlspecialchars($payment['order_id']) ?>">
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
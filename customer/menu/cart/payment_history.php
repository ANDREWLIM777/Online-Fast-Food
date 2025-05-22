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
    header("Location: ../../login.php?message=Session+expired");
    exit();
}
$_SESSION['last_activity'] = time();

// Check database connection
if ($conn->connect_error) {
    $logMessage("Database connection failed: " . $conn->connect_error);
    header("Location: ../../error.php?message=Database+connection+failed");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    $logMessage("No customer_id in session");
    header("Location: ../../login.php");
    exit();
}

$customerId = (int)$_SESSION['customer_id'];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Fetch payment history with pagination
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;
$csrf_param = urlencode($csrfToken);

$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM payment_history
    WHERE customer_id = ?
");
if (!$stmt) {
    $logMessage("Prepare failed for count: " . $conn->error);
    header("Location: ../../error.php?message=Database+error");
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
    header("Location: ../../error.php?message=Database+error");
    exit();
}
$stmt->bind_param("iii", $customerId, $records_per_page, $offset);
$stmt->execute();
$payment_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to get status color
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'completed':
            return 'text-green-600';
        case 'pending':
            return 'text-yellow-600';
        case 'failed':
            return 'text-red-600';
        case 'refunded':
            return 'text-blue-600';
        default:
            return 'text-gray-600';
    }
}

// Function to format delivery address
function formatDeliveryAddress($address) {
    if (empty($address)) {
        return 'N/A';
    }
    $decoded = json_decode($address, true);
    if ($decoded && is_array($decoded)) {
        $addressParts = [];
        if (!empty($decoded['street_address'])) {
            $addressParts[] = htmlspecialchars($decoded['street_address']);
        }
        if (!empty($decoded['city'])) {
            $addressParts[] = htmlspecialchars($decoded['city']);
        }
        if (!empty($decoded['postal_code'])) {
            $addressParts[] = htmlspecialchars($decoded['postal_code']);
        }
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
        .table-container { overflow-x: auto; }
        tr { transition: background-color 0.2s ease; }
        tr:hover { background-color: #f3f4f6; }
        .btn-primary { background-color: #f97316; color: white; }
        .btn-primary:hover { background-color: #ea580c; }
        .text-primary { color: #f97316; }
        .text-primary:hover { color: #ea580c; }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="../../index.php" class="text-primary hover:text-primary flex items-center">
                <i class="fas fa-home mr-2"></i> Back to Home
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Payment History</h2>

            <?php if (empty($payment_history)): ?>
                <p class="text-gray-600">No payment records found. <a href="../../index.php" class="text-primary hover:text-primary">Start shopping now</a>.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="w-full table-auto border-collapse" role="grid" aria-label="Payment history">
                        <thead>
                            <tr class="bg-gray-50">
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Order ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Amount</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Payment Method</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Payment Details</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Delivery Method</th>
                                <th scope="col" class="px-4 py-3 text-left text-sm font-medium text-gray-700">Delivery Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_history as $payment): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <a href="confirmation.php?order_id=<?= urlencode($payment['order_id']) ?>&csrf_token=<?= $csrf_param ?>" class="text-primary hover:underline">
                                            <?= htmlspecialchars($payment['order_id']) ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= date('d M Y, H:i', strtotime($payment['date'])) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600">RM <?= number_format($payment['amount'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm <?= getStatusColor($payment['status']) ?>">
                                        <?= ucfirst(htmlspecialchars($payment['status'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <i class="fas <?= $payment['method'] === 'card' ? 'fa-credit-card' : ($payment['method'] === 'online_banking' ? 'fa-university' : 'fa-wallet') ?> mr-2"></i>
                                        <?= ucfirst(htmlspecialchars($payment['method'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($payment['payment_details'] ?: 'N/A') ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <i class="fas <?= $payment['delivery_method'] === 'delivery' ? 'fa-truck' : 'fa-store' ?> mr-2"></i>
                                        <?= ucfirst(htmlspecialchars($payment['delivery_method'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= formatDeliveryAddress($payment['delivery_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="mt-6 flex justify-between items-center" aria-label="Pagination">
                        <p class="text-sm text-gray-600">
                            Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> records
                        </p>
                        <div class="flex space-x-2">
                            <a href="?page=<?= max(1, $page - 1) ?>&csrf_token=<?= $csrf_param ?>" class="px-4 py-2 btn-primary rounded-lg flex items-center <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>" aria-label="Previous page" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <span class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg">Page <?= $page ?> of <?= $total_pages ?></span>
                            <a href="?page=<?= min($total_pages, $page + 1) ?>&csrf_token=<?= $csrf_param ?>" class="px-4 py-2 btn-primary rounded-lg flex items-center <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>" aria-label="Next page" <?= $page >= $total_pages ? 'aria-disabled="true"' : '' ?>>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php
ob_end_flush();
?>
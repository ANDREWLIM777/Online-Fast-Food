<?php
session_start();
require '../db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: ../../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];

// Fetch payment history with pagination
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM payment_history
    WHERE customer_id = ?
");
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
$stmt->bind_param("iii", $customerId, $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$payment_history = $result->fetch_all(MYSQLI_ASSOC);
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
        body {
            font-family: 'Inter', sans-serif;
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .table-container {
            overflow-x: auto;
        }
        tr {
            transition: background-color 0.2s ease;
        }
        tr:hover {
            background-color: #F9FAFB;
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-800">Brizo Fast Food Melaka</h1>
            <a href="cart.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Cart
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Payment History</h2>

            <?php if (empty($payment_history)): ?>
                <p class="text-gray-600">No payment history available.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-blue-50">
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Order ID</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Date</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Amount</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Method</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Details</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Delivery</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_history as $payment): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($payment['order_id']) ?></td>
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
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($payment['delivery_address'] ?: 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex justify-between items-center">
                        <p class="text-sm text-gray-600">
                            Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> records
                        </p>
                        <div class="flex space-x-2">
                            <a href="?page=<?= max(1, $page - 1) ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>" aria-label="Previous Page">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <span class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg">Page <?= $page ?> of <?= $total_pages ?></span>
                            <a href="?page=<?= min($total_pages, $page + 1) ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>" aria-label="Next Page">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
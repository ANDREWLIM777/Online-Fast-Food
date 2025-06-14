<?php
ob_start();
session_start();
require '../db_connect.php';

// Debug mode
$debug = true;
$logFile = 'feedback_history_errors.log';
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
$csrfParam = urlencode($csrfToken);

// Validate CSRF token for GET requests
if (isset($_GET['csrf_token']) && $_GET['csrf_token'] !== $csrfToken) {
    $logMessage("Invalid CSRF token for page: " . ($_GET['page'] ?? 'unknown'));
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Invalid CSRF token"));
    exit();
}

// Fetch feedback history with pagination
$recordsPerPage = 10;
$page = max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($page - 1) * $recordsPerPage;

// Count total records
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM feedback f
    INNER JOIN orders o ON f.order_id = o.order_id
    WHERE f.customer_id = ?
");
if (!$stmt) {
    $logMessage("Prepare failed for count: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch feedback history
$stmt = $conn->prepare("
    SELECT f.order_id, f.rating, f.comments, f.evidence_path, f.created_at, o.total
    FROM feedback f
    INNER JOIN orders o ON f.order_id = o.order_id
    WHERE f.customer_id = ?
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
");
if (!$stmt) {
    $logMessage("Prepare failed for feedback history: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("iii", $customerId, $recordsPerPage, $offset);
$stmt->execute();
$feedbackHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Log page access
$logMessage("Feedback history accessed for customer_id: $customerId, page: $page");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback History - Brizo Fast Food Melaka</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
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
        }
        .history-table tr:nth-child(even) { background-color: #f9fafb; }
        .history-table tr:hover { background-color: #f1f5f9; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            color: white;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: none;
            height: 36px;
        }
        .btn-primary { background-color: #ff4757; }
        .btn-primary:hover { background-color: #e63946; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); }
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
        .star-filled { color: #f59e0b; }
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
        <div class="card p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Feedback History</h2>
            <p class="text-gray-600 mb-4">Customer ID: <?= htmlspecialchars($customerId) ?></p>

            <?php if (empty($feedbackHistory)): ?>
                <p class="text-gray-600">No feedback history found.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="history-table" role="grid" aria-label="Feedback history">
                        <thead>
                            <tr>
                                <th scope="col">Order ID</th>
                                <th scope="col">Rating</th>
                                <th scope="col">Comments</th>
                                <th scope="col">Evidence</th>
                                <th scope="col">Submitted On</th>
                                <th scope="col">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedbackHistory as $row): ?>
                                <tr>
                                    <td>
                                        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/confirmation.php?order_id=<?= urlencode($row['order_id']) ?>&csrf_token=<?= $csrfParam ?>" class="text-primary hover:text-primary">
                                            <?= htmlspecialchars($row['order_id']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= $row['rating'] ? 'star-filled' : 'text-gray-500' ?>"></i>
                                        <?php endfor; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['comments'] ?: 'None') ?></td>
                                    <td>
                                        <?php if ($row['evidence_path']): ?>
                                            <a href="<?= htmlspecialchars($row['evidence_path']) ?>" target="_blank" class="text-primary hover:text-primary">View Image</a>
                                        <?php else: ?>
                                            None
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d M Y, H:i:s', strtotime($row['created_at'])) ?>
                                    </td>
                                    <td>RM <?= number_format($row['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-6 flex justify-between items-center">
                        <div>
                            <p class="text-gray-600">
                                Showing <?= ($offset + 1) ?> to <?= min($offset + $recordsPerPage, $totalRecords) ?> of <?= $totalRecords ?> records
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <a href="?page=<?= max(1, $page - 1) ?>&csrf_token=<?= $csrfParam ?>" class="btn btn-primary <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?> aria-label="Previous page">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            <span class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </span>
                            <a href="?page=<?= min($totalPages, $page + 1) ?>&csrf_token=<?= $csrfParam ?>" class="btn btn-primary <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $page >= $totalPages ? 'aria-disabled="true"' : '' ?> aria-label="Next page">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Back to Feedback Button -->
            <div class="mt-6 text-right">
                <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/feedback.php?csrf_token=<?= $csrfParam ?>" class="btn btn-primary flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i> Feedback
                </a>
            </div>
        </div>
    </main>
</body>
</html>
<?php
ob_end_flush();
?>
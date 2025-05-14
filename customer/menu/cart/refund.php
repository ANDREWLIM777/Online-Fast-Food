<?php
ob_start();
session_start();
require '../db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: ../../login.php");
    exit();
}

$customerId = $_SESSION['customer_id'];

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Log file for debugging
$logFile = 'refund_errors.log';
$logMessage = function($message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
};

// Check if refund_requests table exists
$checkTableStmt = $conn->query("SHOW TABLES LIKE 'refund_requests'");
if ($checkTableStmt->num_rows === 0) {
    $logMessage("refund_requests table does not exist in the database");
    $errorMessage = "The refund system is currently unavailable. Please contact support.";
    $eligibleOrders = [];
    $refundHistory = [];
} else {
    $errorMessage = null;

    // Fetch eligible orders for refund
    $eligibleOrders = [];
    try {
        $stmt = $conn->prepare("
            SELECT ph.order_id, ph.date, ph.amount, ph.method, ph.payment_details, ph.status
            FROM payment_history ph
            WHERE ph.customer_id = ? 
            AND ph.status = 'completed'
            AND ph.date >= NOW() - INTERVAL 7 DAY
            AND NOT EXISTS (
                SELECT 1 FROM refund_requests r WHERE r.order_id = ph.order_id
            )
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $eligibleOrders[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $logMessage("Error fetching eligible orders: " . $e->getMessage());
        $errorMessage = "Unable to load eligible orders. Please try again later.";
        $eligibleOrders = [];
    }

    // Fetch refund history
    $refundHistory = [];
    try {
        $stmt = $conn->prepare("
            SELECT r.order_id, r.total, r.reason, r.details, r.status, r.created_at, r.updated_at, r.admin_notes, r.evidence_path
            FROM refund_requests r
            WHERE r.customer_id = ?
            ORDER BY r.created_at DESC
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $refundHistory[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $logMessage("Error fetching refund history: " . $e->getMessage());
        $errorMessage = $errorMessage ?: "Unable to load refund history. Please try again later.";
        $refundHistory = [];
    }
}

// Handle refund request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_refund'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed for refund request");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    if ($checkTableStmt->num_rows === 0) {
        $logMessage("Refund request attempted but refund_requests table does not exist");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Refund system is unavailable']);
        exit();
    }

    $orderId = $_POST['order_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $details = trim($_POST['details'] ?? '');
    $logMessage("Received refund request for order_id: $orderId, reason: $reason");

    // Validate inputs
    if (empty($orderId)) {
        $logMessage("Validation failed: No order ID provided");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please select an order']);
        exit();
    }
    if (!in_array($reason, ['wrong-item', 'poor-quality', 'late-delivery', 'other'])) {
        $logMessage("Validation failed: Invalid reason provided");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid reason']);
        exit();
    }
    if (empty($details) || strlen($details) < 10) {
        $logMessage("Validation failed: Invalid details provided");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please provide details (minimum 10 characters)']);
        exit();
    }

    // Handle file upload
    $evidencePath = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['evidence'];
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if (!in_array($file['type'], $allowedTypes)) {
            $logMessage("Validation failed: Invalid file type for evidence");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Evidence must be a JPEG, PNG, or PDF']);
            exit();
        }
        if ($file['size'] > $maxSize) {
            $logMessage("Validation failed: Evidence file too large");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Evidence file must be under 5MB']);
            exit();
        }
        $uploadDir = 'uploads/refunds/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = uniqid('evidence_') . '_' . basename($file['name']);
        $evidencePath = $uploadDir . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $evidencePath)) {
            $logMessage("Failed to upload evidence file");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload evidence']);
            exit();
        }
    }

    try {
        // Verify order eligibility and fetch total and items
        $stmt = $conn->prepare("
            SELECT ph.amount, ph.method, ph.payment_details
            FROM payment_history
            WHERE order_id = ? AND customer_id = ? AND status = 'completed'
            AND date >= NOW() - INTERVAL 7 DAY
            AND NOT EXISTS (
                SELECT 1 FROM refund_requests WHERE order_id = ?
            )
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("sis", $orderId, $customerId, $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $logMessage("Order $orderId is not eligible for refund");
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Order is not eligible for refund']);
            exit();
        }
        $order = $result->fetch_assoc();
        $stmt->close();

        // Fetch order items
        $items = [];
        $stmt = $conn->prepare("
            SELECT oi.item_id, oi.quantity, oi.price, m.item_name
            FROM order_items oi
            JOIN menu_items m ON oi.item_id = m.id
            WHERE oi.order_id = ?
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'item_id' => $row['item_id'],
                'item_name' => $row['item_name'],
                'quantity' => $row['quantity'],
                'price' => $row['price']
            ];
        }
        $stmt->close();
        $itemsJson = json_encode($items);

        // Insert refund request
        $stmt = $conn->prepare("
            INSERT INTO refund_requests (order_id, customer_id, total, reason, details, status, evidence_path, items, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $total = $order['amount'];
        $stmt->bind_param("sidsssis", $orderId, $customerId, $total, $reason, $details, $evidencePath, $itemsJson);
        if ($stmt->execute()) {
            $logMessage("Refund request submitted for order_id: $orderId");
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Refund request submitted successfully']);
        } else {
            $logMessage("Failed to submit refund request for order_id: $orderId - " . $stmt->error);
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Failed to submit refund request']);
        }
        $stmt->close();
        exit();
    } catch (Exception $e) {
        $logMessage("Exception while processing refund request: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// Function to get refund status color
function getRefundStatusColor($status) {
    switch (strtolower($status)) {
        case 'approved':
            return 'text-green-600';
        case 'pending':
            return 'text-yellow-600';
        case 'rejected':
            return 'text-red-600';
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
    <title>Request Refund - Brizo Fast Food Melaka</title>
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
        .message {
            transition: opacity 0.3s ease-in-out;
        }
        .invalid {
            border-color: #EF4444 !important;
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
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Request a Refund</h2>

            <!-- Error Message if Table Missing -->
            <?php if ($errorMessage): ?>
                <div class="message p-4 bg-red-100 text-red-800 rounded-lg mb-6">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

            <!-- Message Display -->
            <div id="message" class="message hidden p-4 rounded-lg mb-6"></div>

            <!-- Refund Request Form -->
            <section class="mb-8 bg-gray-50 p-6 rounded-lg">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Submit Refund Request</h3>
                <form id="refundForm" class="space-y-4" enctype="multipart/form-data" <?php if ($errorMessage) echo 'style="opacity:0.5; pointer-events:none;"'; ?>>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div>
                        <label for="orderId" class="block text-gray-700 font-medium">Select Order</label>
                        <select id="orderId" name="order_id" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Select Order" onchange="validateRefundForm()" <?php if ($errorMessage) echo 'disabled'; ?>>
                            <option value="">Select an order</option>
                            <?php foreach ($eligibleOrders as $order): ?>
                                <option value="<?= htmlspecialchars($order['order_id']) ?>">
                                    <?= htmlspecialchars($order['order_id']) ?> - RM <?= number_format($order['amount'], 2) ?> (<?= date('d M Y', strtotime($order['date'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="reason" class="block text-gray-700 font-medium">Reason for Refund</label>
                        <select id="reason" name="reason" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Refund Reason" onchange="validateRefundForm()" <?php if ($errorMessage) echo 'disabled'; ?>>
                            <option value="">Select a reason</option>
                            <option value="wrong-item">Wrong Item</option>
                            <option value="poor-quality">Poor Quality</option>
                            <option value="late-delivery">Late Delivery</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="details" class="block text-gray-700 font-medium">Details</label>
                        <textarea id="details" name="details" placeholder="Please provide details for your refund request (minimum 10 characters)" class="w-full p-3 border border-gray-300 rounded-lg" rows="4" oninput="validateRefundForm()" aria-label="Refund Details" <?php if ($errorMessage) echo 'disabled'; ?>></textarea>
                    </div>
                    <div>
                        <label for="evidence" class="block text-gray-700 font-medium">Upload Evidence (optional, JPEG/PNG/PDF, max 5MB)</label>
                        <input type="file" id="evidence" name="evidence" accept="image/jpeg,image/png,application/pdf" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Upload Evidence" <?php if ($errorMessage) echo 'disabled'; ?>>
                    </div>
                    <button type="button" id="submitRefundButton" onclick="submitRefundRequest()" disabled class="w-full bg-blue-600 text-white p-3 rounded-lg hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">Submit Refund Request</button>
                </form>
            </section>

            <!-- Refund History -->
            <section>
                <h3 class="text-xl font-medium text-gray-700 mb-4">Refund History</h3>
                <?php if (empty($refundHistory)): ?>
                    <p class="text-gray-600">No refund requests found.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="w-full table-auto border-collapse">
                            <thead>
                                <tr class="bg-blue-50">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Order ID</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Request Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Reason</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Details</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Admin Notes</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refundHistory as $refund): ?>
                                    <tr class="border-b border-gray-200">
                                        <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($refund['order_id']) ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600"><?= date('d M Y, H:i', strtotime($refund['created_at'])) ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">RM <?= number_format($refund['total'], 2) ?></td>
                                        <td class="px-4 py-3 text-sm <?= getRefundStatusColor($refund['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($refund['status'])) ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600"><?= ucfirst(str_replace('-', ' ', htmlspecialchars($refund['reason']))) ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($refund['details']) ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?= $refund['admin_notes'] ? htmlspecialchars($refund['admin_notes']) : 'N/A' ?>
                                            <?= $refund['updated_at'] ? '<br>(' . date('d M Y, H:i', strtotime($refund['updated_at'])) . ')' : '' ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <?= $refund['evidence_path'] ? '<a href="/Online-Fast-Food/customer/menu/cart/' . htmlspecialchars($refund['evidence_path']) . '" target="_blank" class="text-blue-600 hover:underline">View</a>' : 'N/A' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script>
        // Initialize elements
        const refundForm = document.getElementById('refundForm');
        const orderIdSelect = document.getElementById('orderId');
        const reasonSelect = document.getElementById('reason');
        const detailsInput = document.getElementById('details');
        const evidenceInput = document.getElementById('evidence');
        const submitRefundButton = document.getElementById('submitRefundButton');
        const messageDiv = document.getElementById('message');

        // Validate refund form
        function validateRefundForm() {
            const orderId = orderIdSelect.value;
            const reason = reasonSelect.value;
            const details = detailsInput.value.trim();
            const isValid = orderId !== '' && reason !== '' && details.length >= 10;

            if (details.length < 10 && details !== '') {
                detailsInput.classList.add('invalid');
            } else {
                detailsInput.classList.remove('invalid');
            }

            submitRefundButton.disabled = !isValid || <?php echo $errorMessage ? 'true' : 'false'; ?>;
        }

        // Show message to user
        function showMessage(type, message) {
            messageDiv.className = `message p-4 rounded-lg ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
            messageDiv.textContent = message;
            messageDiv.classList.remove('hidden');
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 5000);
        }

        // Submit refund request
        function submitRefundRequest() {
            <?php if ($errorMessage): ?>
                showMessage('error', 'Refund system is unavailable');
                return;
            <?php endif; ?>

            const formData = new FormData(refundForm);
            formData.append('request_refund', '1');

            fetch('refund.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    refundForm.reset();
                    orderIdSelect.innerHTML = '<option value="">Select an order</option>';
                    submitRefundButton.disabled = true;

                    // Update refund history table
                    const tableBody = document.querySelector('tbody') || document.createElement('tbody');
                    if (!document.querySelector('table')) {
                        const tableContainer = document.querySelector('.table-container') || document.createElement('div');
                        tableContainer.className = 'table-container';
                        const table = document.createElement('table');
                        table.className = 'w-full table-auto border-collapse';
                        table.innerHTML = `
                            <thead>
                                <tr class="bg-blue-50">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Order ID</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Request Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Reason</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Details</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Admin Notes</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Evidence</th>
                                </tr>
                            </thead>
                        `;
                        table.appendChild(tableBody);
                        tableContainer.appendChild(table);
                        document.querySelector('section:last-of-type').appendChild(tableContainer);
                    }

                    const newRow = document.createElement('tr');
                    newRow.className = 'border-b border-gray-200';
                    const reasonText = formData.get('reason').replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    newRow.innerHTML = `
                        <td class="px-4 py-3 text-sm text-gray-600">${formData.get('order_id')}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">${new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">RM ${parseFloat(document.querySelector(`option[value="${formData.get('order_id')}"]`)?.text.match(/RM (\d+\.\d{2})/)?.[1] || '0.00').toFixed(2)}</td>
                        <td class="px-4 py-3 text-sm text-yellow-600">Pending</td>
                        <td class="px-4 py-3 text-sm text-gray-600">${reasonText}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">${formData.get('details').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">N/A</td>
                        <td class="px-4 py-3 text-sm text-gray-600">${formData.has('evidence') && formData.get('evidence').size > 0 ? 'Uploaded' : 'N/A'}</td>
                    `;
                    tableBody.insertBefore(newRow, tableBody.firstChild);
                } else {
                    showMessage('error', data.message);
                }
            })
            .catch(error => {
                showMessage('error', 'An error occurred while submitting refund request');
            });
        }

        // Initialize form validation
        orderIdSelect.addEventListener('change', validateRefundForm);
        reasonSelect.addEventListener('change', validateRefundForm);
        detailsInput.addEventListener('input', validateRefundForm);
        evidenceInput.addEventListener('change', validateRefundForm);
        validateRefundForm();
    </script>
</body>
</html>
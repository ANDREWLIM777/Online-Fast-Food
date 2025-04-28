<?php
session_start();
require '../db_connect.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

// Fetch payment history for the customer
$paymentHistory = [];
$stmt = $conn->prepare("
    SELECT order_id, date, amount, status, method
    FROM payment_history
    WHERE customer_id = ?
    ORDER BY date DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $paymentHistory[] = $row;
}

// Handle export to CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_history'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_history_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Write CSV headers
    fputcsv($output, ['Date', 'Order ID', 'Amount (RM)', 'Status', 'Method']);
    
    // Write payment history data
    foreach ($paymentHistory as $payment) {
        fputcsv($output, [
            $payment['date'],
            $payment['order_id'],
            number_format($payment['amount'], 2),
            $payment['status'],
            $payment['method']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History - Brizo Fast Food Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f8f8;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #2980b9;
        }
        .back-cart {
            display: inline-block;
            margin: 20px 0;
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="cart.php" class="back-cart">⬅️ Back to Cart</a>
        <h2>Payment History</h2>
        <button onclick="exportHistory()">Export History</button>

        <?php if (empty($paymentHistory)): ?>
            <p>No payment history available.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order ID</th>
                        <th>Amount (RM)</th>
                        <th>Status</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentHistory as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars($payment['date']) ?></td>
                            <td><?= htmlspecialchars($payment['order_id']) ?></td>
                            <td><?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payment['status']) ?></td>
                            <td><?= htmlspecialchars($payment['method']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        window.exportHistory = function() {
            // Submit a form to trigger the CSV export
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'export_history';
            input.value = 'true';
            form.appendChild(input);
            
            document.body.appendChild(form);
            form.submit();
            
            // Optional: Show a confirmation message
            setTimeout(() => {
                alert('Payment history exported successfully!');
            }, 500);
        };
    </script>
</body>
</html>
<?php
session_start();
require '../db_connect.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    file_put_contents('payment_history_errors.log', date('Y-m-d H:i:s') . ' - No customer_id in session, redirecting to login.php' . PHP_EOL, FILE_APPEND);
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

// Log the customer ID in session
file_put_contents('payment_history_errors.log', date('Y-m-d H:i:s') . ' - Customer ID in session: ' . ($customerId ?? 'not set') . PHP_EOL, FILE_APPEND);

// Fetch payment history for the customer
try {
    $stmt = $conn->prepare("
        SELECT ph.order_id, ph.date, ph.amount, ph.method, ph.payment_details, ph.delivery_method, ph.delivery_address, ph.status
        FROM payment_history ph
        WHERE ph.customer_id = ?
        ORDER BY ph.date DESC
    ");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    file_put_contents('payment_history_errors.log', date('Y-m-d H:i:s') . ' - Error fetching payment history: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    die('Error: Unable to fetch payment history');
}

// Process items and refund details for each payment
$paymentDetails = [];
foreach ($payments as $payment) {
    $orderId = $payment['order_id'];

    // Fetch order items
    try {
        $stmt = $conn->prepare("
            SELECT oi.item_id, oi.quantity, oi.price, m.item_name, m.photo
            FROM order_items oi
            LEFT JOIN menu_items m ON oi.item_id = m.id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        file_put_contents('payment_history_errors.log', date('Y-m-d H:i:s') . ' - Error fetching items for order_id: ' . $orderId . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        $items = [];
    }

    // Fetch all refund requests for this order
    $refundDetails = [];
    try {
        $stmt = $conn->prepare("
            SELECT id, status, total, items, admin_notes, created_at
            FROM refund_requests
            WHERE order_id = ? AND customer_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("si", $orderId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $refundDetails[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        file_put_contents('payment_history_errors.log', date('Y-m-d H:i:s') . ' - Error fetching refund details for order_id: ' . $orderId . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }

    $paymentDetails[] = [
        'payment' => $payment,
        'items' => $items,
        'refund_details' => $refundDetails
    ];
}

// Base URL for images
$imageBaseUrl = '/Online-Fast-Food/Admin/Manage_Menu_Item/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Brizo Fast Food Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            margin: 20px 0;
            color: #3498db;
            text-decoration: none;
            font-size: 16px;
        }
        .back-link:hover {
            text-decoration: underline;
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
            font-weight: bold;
        }
        .delivery-address {
            color: #666;
            font-size: 0.9em;
            white-space: pre-wrap;
        }
        .items-table {
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .items-table th, .items-table td {
            padding: 8px;
        }
        .cart-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .refund-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .refund-btn:hover {
            background: #c0392b;
        }
        .refund-details {
            margin-top: 10px;
            font-size: 0.9em;
            color: #555;
        }
        .refund-details ul {
            list-style: none;
            padding: 0;
            margin: 5px 0;
        }
        .refund-details ul li {
            margin-bottom: 5px;
        }
        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }
            .refund-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/Online-Fast-Food/customer/menu/menu.php" class="back-link"><i class="fas fa-arrow-left"></i> Back To Menu</a>
        <h2>Payment History</h2>

        <?php if (empty($paymentDetails)): ?>
            <p>You have no payment history.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Amount (RM)</th>
                        <th>Payment Method</th>
                        <th>Payment Details</th>
                        <th>Delivery Method</th>
                        <th>Delivery Address</th>
                        <th>Items</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentDetails as $detail): ?>
                        <?php $payment = $detail['payment']; ?>
                        <tr>
                            <td><?= htmlspecialchars($payment['order_id']) ?></td>
                            <td><?= htmlspecialchars($payment['date']) ?></td>
                            <td><?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars(ucfirst($payment['method'])) ?></td>
                            <td><?= htmlspecialchars($payment['payment_details'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(ucfirst($payment['delivery_method'])) ?></td>
                            <td>
                                <?php if ($payment['delivery_method'] === 'delivery' && !empty($payment['delivery_address'])): ?>
                                    <span class="delivery-address"><?= htmlspecialchars($payment['delivery_address']) ?></span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($detail['items'])): ?>
                                    No items found
                                <?php else: ?>
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>Photo</th>
                                                <th>Item Name</th>
                                                <th>Quantity</th>
                                                <th>Price (RM)</th>
                                                <th>Subtotal (RM)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detail['items'] as $item): ?>
                                                <tr>
                                                    <td class="cart-item">
                                                        <img src="<?= htmlspecialchars($imageBaseUrl . ($item['photo'] ?? 'Uploads/default-food-image.jpg')) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                                                    </td>
                                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                                                    <td><?= number_format($item['price'], 2) ?></td>
                                                    <td><?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="refund.php?order_id=<?= urlencode($payment['order_id']) ?>" class="refund-btn">Request Refund</a>
                                <?php if (!empty($detail['refund_details'])): ?>
                                    <?php foreach ($detail['refund_details'] as $refund): ?>
                                        <div class="refund-details">
                                            <strong>Refund Status:</strong> <?= htmlspecialchars(ucfirst($refund['status'])) ?><br>
                                            <strong>Request Date:</strong> <?= htmlspecialchars($refund['created_at']) ?><br>
                                            <?php if ($refund['total'] !== null): ?>
                                                <strong>Refund Amount:</strong> RM <?= number_format($refund['total'], 2) ?><br>
                                            <?php endif; ?>
                                            <?php if ($refund['items']): ?>
                                                <strong>Refund Items:</strong>
                                                <?php
                                                $refundItems = json_decode($refund['items'], true);
                                                if ($refundItems): ?>
                                                    <ul>
                                                        <?php foreach ($refundItems as $item): ?>
                                                            <li><?= htmlspecialchars($item['item_name']) ?> (Qty: <?= $item['quantity'] ?>, RM <?= number_format($item['price'], 2) ?>)</li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($refund['admin_notes']): ?>
                                                <strong>Admin Notes:</strong> <?= htmlspecialchars($refund['admin_notes']) ?><br>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
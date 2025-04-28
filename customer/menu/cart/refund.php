<?php
session_start();
require '../db_connect.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header("Location: /Online-Fast-Food/customer/login.php");
    exit();
}

// Handle refund request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund'])) {
    $orderId = $_POST['refund_order'];
    $reason = $_POST['refund_reason'];
    $details = $_POST['refund_details'];

    // Validate that the order belongs to the customer
    $stmt = $conn->prepare("SELECT id FROM payment_history WHERE order_id = ? AND customer_id = ?");
    $stmt->bind_param("si", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
        exit();
    }

    // Save refund request to refund_requests table
    $stmt = $conn->prepare("
        INSERT INTO refund_requests (customer_id, order_id, reason, details, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("isss", $customerId, $orderId, $reason, $details);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit refund request']);
    }
    exit();
}

// Fetch orders for refund (from payment_history)
$orders = [];
$stmt = $conn->prepare("
    SELECT order_id, date
    FROM payment_history
    WHERE customer_id = ?
    ORDER BY date DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Refund - Brizo Fast Food Melaka</title>
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        select.error, textarea.error {
            border-color: #e74c3c;
        }
        .error-message {
            color: #e74c3c;
            font-size: 0.8em;
            display: none;
        }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .hidden {
            display: none;
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
        <h2>Request Refund</h2>
        <div id="refund-alert"></div>
        <div class="form-group">
            <label for="refund-order">Order ID</label>
            <select id="refund-order">
                <option value="">Select an order</option>
                <?php foreach ($orders as $order): ?>
                    <option value="<?= htmlspecialchars($order['order_id']) ?>">
                        <?= htmlspecialchars($order['order_id']) ?> (<?= htmlspecialchars($order['date']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="refund-order-error" class="error-message"></div>
        </div>
        <div class="form-group">
            <label for="refund-reason">Reason for Refund</label>
            <select id="refund-reason">
                <option value="">Select a reason</option>
                <option value="wrong-item">Wrong item delivered</option>
                <option value="poor-quality">Poor quality</option>
                <option value="late-delivery">Late delivery</option>
                <option value="other">Other</option>
            </select>
            <div id="refund-reason-error" class="error-message"></div>
        </div>
        <div class="form-group">
            <label for="refund-details">Additional Details</label>
            <textarea id="refund-details" rows="4"></textarea>
            <div id="refund-details-error" class="error-message"></div>
        </div>
        <button onclick="submitRefundRequest()">Submit Refund Request</button>
    </div>

    <script>
        window.submitRefundRequest = function() {
            document.querySelectorAll('.error-message').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('select, textarea').forEach(el => {
                el.classList.remove('error');
            });

            const orderId = document.getElementById('refund-order').value;
            const reason = document.getElementById('refund-reason').value;
            const details = document.getElementById('refund-details').value.trim();

            let isValid = true;

            if (!orderId) {
                document.getElementById('refund-order-error').textContent = 'Please select an order';
                document.getElementById('refund-order-error').style.display = 'block';
                document.getElementById('refund-order').classList.add('error');
                isValid = false;
            }

            if (!reason) {
                document.getElementById('refund-reason-error').textContent = 'Please select a reason for refund';
                document.getElementById('refund-reason-error').style.display = 'block';
                document.getElementById('refund-reason').classList.add('error');
                isValid = false;
            }

            if (!details) {
                document.getElementById('refund-details-error').textContent = 'Please provide additional details';
                document.getElementById('refund-details-error').style.display = 'block';
                document.getElementById('refund-details').classList.add('error');
                isValid = false;
            }

            if (!isValid) {
                return;
            }

            const formData = new FormData();
            formData.append('submit_refund', 'true');
            formData.append('refund_order', orderId);
            formData.append('refund_reason', reason);
            formData.append('refund_details', details);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('refund-alert', 'Refund request submitted successfully! Our team will review it shortly.', 'success');
                    document.getElementById('refund-order').value = '';
                    document.getElementById('refund-reason').value = '';
                    document.getElementById('refund-details').value = '';
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert('refund-alert', data.message || 'Failed to submit refund request. Please try again.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('refund-alert', 'An error occurred. Please try again.', 'danger');
            });
        };

        function showAlert(containerId, message, type) {
            const container = document.getElementById(containerId);
            container.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `;
            container.classList.remove('hidden');
            setTimeout(() => {
                container.classList.add('hidden');
            }, 5000);
        }
    </script>
</body>
</html>
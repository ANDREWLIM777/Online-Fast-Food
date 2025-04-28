<?php
session_start();
$order = $_SESSION['last_order'] ?? null;
if (!$order) {
    header("Location: cart.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        a { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Order Confirmed!</h2>
        <p>Order ID: <?= htmlspecialchars($order['order_id']) ?></p>
        <p>Amount: RM <?= number_format($order['amount'], 2) ?></p>
        <p>Payment Method: <?= htmlspecialchars(ucfirst($order['method'])) ?></p>
        <p>Time: <?= htmlspecialchars($order['timestamp']) ?></p>
        <a href="cart.php">Back to Cart</a>
    </div>
</body>
</html>
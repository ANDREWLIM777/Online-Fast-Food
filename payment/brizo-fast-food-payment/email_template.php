<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ff4757; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .footer { margin-top: 20px; padding: 10px; text-align: center; font-size: 12px; color: #777; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
            <p>Thank you for your order!</p>
        </div>
        
        <div class="content">
            <h2>Order Details</h2>
            <p><strong>Order ID:</strong> <?= htmlspecialchars($orderCode) ?></p>
            <p><strong>Date:</strong> <?= date('F j, Y, g:i a', strtotime($order['timestamp'])) ?></p>
            <p><strong>Total Amount:</strong> RM <?= number_format($order['amount'], 2) ?></p>
            
            <h3>Delivery Information</h3>
            <p><strong>Method:</strong> <?= ucfirst($order['delivery_method']) ?></p>
            <?php if ($order['delivery_method'] === 'delivery' && $order['delivery_address']): ?>
                <?php $addr = json_decode($order['delivery_address'], true); ?>
                <p><strong>Address:</strong> 
                    <?= htmlspecialchars($addr['street_address'] ?? '') ?>, 
                    <?= htmlspecialchars($addr['city'] ?? '') ?>, 
                    <?= htmlspecialchars($addr['postal_code'] ?? '') ?>
                </p>
            <?php endif; ?>
            
            <h3>Order Items</h3>
            <table>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>RM <?= number_format($item['price'], 2) ?></td>
                    <td>RM <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="footer">
            <p>If you have any questions, please contact us at <?= $_ENV['SMTP_FROM_EMAIL'] ?></p>
            <p>&copy; <?= date('Y') ?> Brizo Fast Food Melaka. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
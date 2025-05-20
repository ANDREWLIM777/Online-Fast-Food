<?php
require 'db_conn.php';
include '../auth_cus.php';

if (!isset($_GET['id'])) die('Invalid ID.');

$id = intval($_GET['id']);
$query = "SELECT * FROM customers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die('Customer not found.');

$customer = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Customer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container profile-view">
    <div class="header">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        <h2>Customer Profile</h2>
    </div>

    <div class="profile-card">
        <div class="profile-header">
            <img src="upload/<?= htmlspecialchars($customer['photo']) ?>" alt="Customer Photo" class="square-photo large">
            <h3><?= htmlspecialchars($customer['fullname']) ?></h3>
        </div>

        <div class="profile-details">
    <div class="detail-item">
        <i class="fas fa-venus-mars"></i>
        <div><span class="label">Gender</span><span class="value"><?= $customer['gender'] ?></span></div>
    </div>

    <div class="detail-item">
        <i class="fas fa-birthday-cake"></i>
        <div><span class="label">Age</span><span class="value"><?= $customer['age'] ?></span></div>
    </div>

    <div class="detail-item">
        <i class="fas fa-envelope"></i>
        <div><span class="label">Email</span><span class="value"><?= $customer['email'] ?></span></div>
    </div>

    <div class="detail-item">
        <i class="fas fa-phone"></i>
        <div><span class="label">Phone</span><span class="value"><?= $customer['phone'] ?></span></div>
    </div>

    <!-- 🧭 Address -->
    <div class="detail-item full-width">
        <i class="fas fa-map-marker-alt"></i>
        <div>
            <span class="label">Address</span>
            <span class="value multi-line"><?= nl2br(htmlspecialchars($customer['address'])) ?></span>
        </div>
    </div>

    <!-- 🏙️ City & Postal -->
    <div class="address-grid">
        <div class="detail-item no-icon">
        <i class="fas fa-city"></i>
            <div>
                <span class="label">City</span>
                <span class="value"><?= $customer['city'] ?></span>
            </div>
        </div>

        <div class="detail-item no-icon">
        <i class="fas fa-envelope"></i>
            <div>
                <span class="label">Postal Code</span>
                <span class="value"><?= $customer['postal_code'] ?></span>
            </div>
        </div>
    </div>

    <div class="detail-item">
        <i class="fas fa-calendar-plus"></i>
        <div><span class="label">Joined</span><span class="value"><?= date('M d, Y', strtotime($customer['created_at'])) ?></span></div>
    </div>
</div>

</div>
</body>
</html>

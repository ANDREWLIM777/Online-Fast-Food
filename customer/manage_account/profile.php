<?php
require '../db_connect.php';
session_start();

$customerId = $_SESSION['customer_id'] ?? null;

if (!$customerId) {
    header('Location: ../login.php');
    exit();
}

$stmt = $conn->prepare("SELECT fullname, email, phone, gender, age, address, city, postal_code, photo FROM customers WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "Customer not found.";
    exit();
}

$customer = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Customer Profile - Brizo Fast Food Melaka</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="profile.css">
</head>
<body>
  <div class="profile-container">
    <div class="profile-header">
      <img src="uploads/<?= htmlspecialchars($customer['photo']) ?>" alt="Profile Photo">
      <div>
        <h2><?= htmlspecialchars($customer['fullname']) ?></h2>
        <p><?= htmlspecialchars($customer['email']) ?></p>
      </div>
    </div>

    <div class="profile-details">
      <p><span>Phone:</span> <?= htmlspecialchars($customer['phone']) ?></p>
      <p><span>Gender:</span> <?= htmlspecialchars($customer['gender']) ?: '-' ?></p>
      <p><span>Age:</span> <?= htmlspecialchars($customer['age']) ?: '-' ?></p>
      <p><span>Address:</span> <?= htmlspecialchars($customer['address']) ?: '-' ?></p>
      <p><span>City:</span> <?= htmlspecialchars($customer['city']) ?: '-' ?></p>
      <p><span>Postal Code:</span> <?= htmlspecialchars($customer['postal_code']) ?: '-' ?></p>
      <a href="edit_profile.php" class="edit-profile-link">Edit Profile</a>
      <a href="javascript:history.back()" class="back-btn">Back</a>

    </div>
  </div>
</body>
</html>

<?php
require 'db_connect.php';
session_start();

// Simulate login session (replace this with real login logic)
$customerId = $_SESSION['customer_id'] ?? null;

if (!$customerId) {
    header('Location: ../customer/login.php');
    exit();
}

// Fetch customer info
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Profile - Brizo Fast Food Melaka</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Fredoka', sans-serif;
      background: #fefefe;
      margin: 0;
      padding: 0;
    }
    .profile-container {
      max-width: 700px;
      margin: 40px auto;
      padding: 20px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .profile-header {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .profile-header img {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid #d63f3f;
    }
    .profile-header h2 {
      margin: 0;
      color: #d63f3f;
    }
    .profile-details {
      margin-top: 20px;
    }
    .profile-details p {
      margin: 8px 0;
      font-size: 1em;
    }
    .profile-details span {
      font-weight: bold;
    }
  </style>
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
    </div>
  </div>
</body>
</html>

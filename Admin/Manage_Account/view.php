<!-- Admin/Manage_Account/view.php -->
<?php
require 'db_conn.php';
include '../auth_acc.php';


if (!isset($_GET['id'])) {
    die('Invalid ID.');
}

$id = intval($_GET['id']);
$query = "SELECT * FROM admin WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Admin not found.');
}

$admin = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Admin Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container profile-view">
    <div class="header">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <h2>Admin Profile Details</h2>
    </div>

    <div class="profile-card">
        <div class="profile-header">
            <img src="../Admin_Account/upload/<?= htmlspecialchars($admin['photo']) ?>" 
                 alt="Admin Photo" 
                 class="square-photo large">
            <h3><?= htmlspecialchars($admin['name']) ?></h3>
            <p class="position"><?= htmlspecialchars($admin['position']) ?></p>
        </div>

        <div class="profile-details">
            <div class="detail-item">
                <i class="fas fa-venus-mars"></i>
                <div>
                    <span class="label">Gender</span>
                    <span class="value"><?= htmlspecialchars($admin['gender']) ?></span>
                </div>
            </div>

            <div class="detail-item">
                <i class="fas fa-birthday-cake"></i>
                <div>
                    <span class="label">Age</span>
                    <span class="value"><?= $admin['age'] ?></span>
                </div>
            </div>

            <div class="detail-item">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <span class="label">Role</span>
                    <span class="value"><?= htmlspecialchars($admin['role']) ?></span>
                </div>
            </div>

            <div class="detail-item">
                <i class="fas fa-envelope"></i>
                <div>
                    <span class="label">Email</span>
                    <span class="value"><?= htmlspecialchars($admin['email']) ?></span>
                </div>
            </div>

            <div class="detail-item">
                <i class="fas fa-phone"></i>
                <div>
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($admin['phone']) ?></span>
                </div>
            </div>

            <div class="detail-item">
                <i class="fas fa-calendar-plus"></i>
                <div>
                    <span class="label">Member Since</span>
                    <span class="value"><?= date('M d, Y', strtotime($admin['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
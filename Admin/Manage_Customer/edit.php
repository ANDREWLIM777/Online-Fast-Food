<?php
require 'db_conn.php';
include '../auth_cus.php';
check_permission('superadmin');

if (!isset($_GET['id'])) {
    die('Invalid ID.');
}

$id = intval($_GET['id']);
$query = "SELECT * FROM customers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Customer not found.');
}

$customer = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Customer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container profile-edit">
    <div class="header">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        <h2>Edit Customer Profile</h2>
    </div>

    <form action="update.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $customer['id'] ?>">

        <div class="form-grid">
            <div class="photo-section">
                <div class="photo-preview">
                    <img src="upload/<?= htmlspecialchars($customer['photo']) ?>" 
                         alt="Photo"
                         class="square-photo medium">
                    <label class="upload-label">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="photo" hidden>
                    </label>
                </div>
            </div>

            <div class="form-section">
                <div class="form-group">
                    <label>Full Name</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="fullname" value="<?= htmlspecialchars($customer['fullname']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Gender</label>
                        <div class="styled-select">
                            <i class="fas fa-venus-mars"></i>
                            <select name="gender" required>
                                <option value="male" <?= $customer['gender'] == 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= $customer['gender'] == 'female' ? 'selected' : '' ?>>Female</option>
                            </select>
                            <div class="select-arrow"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <div class="input-with-icon">
                            <i class="fas fa-birthday-cake"></i>
                            <input type="number" name="age" value="<?= $customer['age'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" value="<?= $customer['email'] ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <div class="input-with-icon">
                            <i class="fas fa-phone"></i>
                            <input type="text" name="phone" value="<?= $customer['phone'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-with-icon password-field">
                    <input type="password" name="password" id="password" placeholder="••••••••">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                    </div>
                    <small class="form-note">Leave blank to keep current password</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" value="<?= $customer['city'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" value="<?= $customer['postal_code'] ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3"><?= $customer['address'] ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn save-btn"><i class="fas fa-save"></i> Save Changes</button>
                    <a href="index.php" class="btn cancel-btn"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
<script src="script.js"></script>
</body>
</html>

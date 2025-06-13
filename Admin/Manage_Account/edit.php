<!-- Admin/Manage_Account/edit.php -->
<?php
require 'db_conn.php';
include '../auth_acc.php';
check_permission('superadmin');

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
    <title>Edit Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <style>
.error-box {
    position: fixed;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    background: #ff4d4d;
    color: white;
    padding: 15px 25px;
    border-radius: 0 0 12px 12px;
    font-weight: bold;
    text-align: center;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(255, 0, 0, 0.2);
    opacity: 1;
    transition: opacity 1s ease-out;
    max-width: 600px;
    width: 90%;
}

.error-box.fade-out {
    opacity: 0;
    pointer-events: none;
}
    </style>
        <?php if (isset($_SESSION['error'])): ?>
    <div class="error-box">
        <?= $_SESSION['error'] ?>
        <?php unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>
</head>
<body>
<div class="container profile-edit">
    <div class="header">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <h2>Edit Admin Profile</h2>
    </div>



    <form action="update.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $admin['id'] ?>">

        <div class="form-grid">
            <div class="photo-section">
                <div class="photo-preview">
                    <img src="../Admin_Account/upload/<?= htmlspecialchars($admin['photo']) ?>" 
                         alt="Current Photo"
                         class="square-photo medium">
                    <label class="upload-label">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="photo" hidden>
                    </label>
                </div>
                <p class="photo-note">Click camera icon to upload new photo</p>
            </div>

        
<div class="form-section">
    <div class="form-group">
        <label>Full Name</label>
        <div class="input-with-icon">
            <i class="fas fa-user"></i>
            <input type="text" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Position</label>
            <div class="input-with-icon">
                <i class="fas fa-briefcase"></i>
                <input type="text" name="position" value="<?= htmlspecialchars($admin['position']) ?>" required>
            </div>
        </div>

  <div class="form-group">
    <label>Role</label>
    <?php if ($admin['role'] === 'superadmin'): ?>
        <div class="input-with-icon">
            <i class="fas fa-shield-alt"></i>
            <input type="text" value="superadmin" disabled>
            <input type="hidden" name="role" value="superadmin">
        </div>
        <small class="form-note">Super Admin role cannot be changed</small>
    <?php else: ?>
        <div class="styled-select">
            <i class="fas fa-shield-alt"></i>
            <select name="role" required>
                <option value="admin" <?= $admin['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="superadmin" <?= $admin['role'] == 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
            </select>
            <div class="select-arrow"></div>
        </div>
    <?php endif; ?>
</div>

    <div class="form-row">
        <div class="form-group">
            <label>Email</label>
            <div class="input-with-icon">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Phone</label>
            <div class="input-with-icon">
                <i class="fas fa-phone"></i>
              <input type="text" name="phone"
       value="<?= htmlspecialchars($admin['phone']) ?>"
       pattern="\d{7,11}" maxlength="11"
       title="Phone number must be 7 to 11 digits"
       oninput="this.value = this.value.replace(/\D/g, '')"
       required>
            </div>
        </div>
</div>


    <div class="form-group">
        <label>New Password</label>
        <div class="input-with-icon password-field">
            <input type="password" name="password" id="password"
       placeholder="••••••••"
       onkeydown="return event.key !== ' '"
       oninput="this.value = this.value.replace(/\s/g, '')"
       onpaste="return false">
            <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
        </div>
        <small class="form-note">Leave blank to keep current password</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn save-btn">
            <i class="fas fa-save"></i> Save Changes
        </button>
        <a href="index.php" class="btn cancel-btn">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</div>
        </div>
    </form>
</div>
<script src="script.js">
</script>
</body>
</html>
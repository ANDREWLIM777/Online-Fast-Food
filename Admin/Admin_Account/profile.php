<?php
include '../auth.php';
include 'db.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM admin WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $error = '';

    if (!preg_match('/^\d{7,13}$/', $phone)) {
        $error = "Phone number must be between 7 and 13 digits.";
    }

    if (!$error) {
        if (!empty($_FILES['photo']['name'])) {
            $photo = time() . '_' . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], "upload/" . $photo);

            $update = $conn->prepare("UPDATE admin SET phone = ?, photo = ? WHERE id = ?");
            $update->bind_param("ssi", $phone, $photo, $user_id);
        } else {
            $update = $conn->prepare("UPDATE admin SET phone = ? WHERE id = ?");
            $update->bind_param("si", $phone, $user_id);
        }

        if ($update->execute()) {
            echo "<script>alert('Profile updated successfully.'); window.location.href='../Main Page/main_page.php';</script>";
            exit;
        } else {
            $error = "Failed to update profile.";
        }
    }

    if ($error) {
        echo "<script>alert('$error'); window.history.back();</script>";
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">
   
<head>

<style>

body::after {
  content: '';
  position: fixed;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at 50% 50%, rgba(244, 227, 178, 0.07) 0%, transparent 70%);
  animation: auraPulse 8s infinite;
  pointer-events: none;
  z-index: -1; 
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-image: 
    radial-gradient(circle at 20% 30%, rgba(244, 228, 178, 0.15) 1px, transparent 2px),
    radial-gradient(circle at 80% 70%, rgba(244, 228, 178, 0.15) 1px, transparent 2px);
  background-size: 60px 60px;
  animation: stardust 20s linear infinite;
  pointer-events: none;
  z-index: -2;
}

@keyframes auraPulse {
  0% { transform: scale(0.8); opacity: 0.3; }
  50% { transform: scale(1.2); opacity: 0.08; }
  100% { transform: scale(0.8); opacity: 0.3; }
}

@keyframes stardust {
  0% { background-position: 0 0, 100px 100px; }
  100% { background-position: 100px 100px, 0 0; }
}

.form-back-btn {
    position: absolute;
    left: 15px;
    top: 15px;
    background: linear-gradient(45deg, #0c0a10, #1a1a1a);
    border: 2px solid #c0a23d;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(192, 162, 61, 0.2);
    z-index: 100;
    text-decoration: none !important;
        }

.form-back-btn i {
    color: #c0a23d;
    font-size: 1.4rem;
    margin-right: 2px;
    transition: transform 0.3s ease;
}

.form-back-btn:hover {
    transform: translateX(-3px) scale(1.05);
    border-color: #907722;
    box-shadow: 0 6px 20px rgba(144, 119, 34, 0.3);
}

.form-back-btn:hover i {
    transform: translateX(-2px);
    color: #907722;
}

.form-container {
    position: relative; 
    margin-top: 0px; 
    padding-top: 20px; 
}

</style>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
<div class="form-container">
    <a href="../Main Page/main_page.php" class="form-back-btn">
        <i class="fas fa-chevron-left"></i>
    </a>
    <h2>Edit Profile</h2>
    <form method="POST" enctype="multipart/form-data">
    <label>Full Name</label>
    <input type="text" value="<?= htmlspecialchars($user['name']) ?>" disabled>

    <label>Email</label>
    <input type="text" value="<?= htmlspecialchars($user['email']) ?>" disabled>

    <label>Role</label>
    <input type="text" value="<?= htmlspecialchars($user['role']) ?>" disabled>

    <label>Phone</label>
    <input type="text" name="phone" pattern="\d{7,13}" title="Enter 7 to 13 digits only" value="<?= htmlspecialchars($user['phone']) ?>" required>

    <label>Change Photo</label>
    <input type="file" name="photo" accept="image/*">

    <button type="submit">Update</button>
</form>
</div>
</body>
</html>

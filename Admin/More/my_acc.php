<!-- my_account.php -->
<?php
require '../Admin_Account/db.php';
include '../auth.php';


$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT photo, name, position, phone, role, email FROM admin WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Account - Brizo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      background: #0c0a10;
      color: #eee;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 40px 20px;
    }

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

    .container {
      max-width: 700px;
      margin: 0 auto;
      background: #1a1a1a;
      padding: 30px;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    }
    .header {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  margin-bottom: 40px;
  gap: 250px;
  position: relative;
  border-bottom: 1px solid #333;
  padding-bottom: 15px;
}

.header-title {
  color: #c0a23d;
  font-size: 2.2rem;
  font-weight: 600;
  font-family: 'Playfair Display', serif;
  letter-spacing: 1.2px;
  margin: 0;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: transparent;
  border: 2px solid #c0a23d;
  color: #c0a23d;
  padding: 10px 20px;
  border-radius: 30px;
  font-weight: 600;
  transition: all 0.3s ease;
  font-size: 0.95rem;
  text-decoration: none;
}

.back-btn i {
  transition: transform 0.3s ease;
  font-size: 1.1rem;
}

.back-btn:hover {
  background: #c0a23d;
  color: #000;
}

.back-btn:hover i {
  transform: translateX(-5px);
}
    .profile-photo {
      text-align: center;
      margin-bottom: 25px;
    }
    .profile-photo img {
      width: 160px;
      height: 160px;
      border-radius: 50%;
      border: 3px solid #c0a23d;
      object-fit: cover;
    }
    .info-group {
      margin-bottom: 18px;
    }
    .info-group label {
      font-weight: 600;
      color: #aaa;
      display: block;
      margin-bottom: 5px;
    }
    .info-group .value {
      font-size: 1.1rem;
      color: #fff;
      padding: 10px 14px;
      background: #2a2a2a;
      border-radius: 8px;
    }
  </style>
</head>
<body>

<div class="container">
<div class="header">
  <a href="more.php" class="back-btn">
    <i class="fas fa-arrow-left"></i> Back to More Page
  </a>
  <h2 class="header-title">Personal Detail</h2>
</div>

  <div class="profile-photo">
    <img src="../Admin_Account/upload/<?= htmlspecialchars($user['photo']) ?>" alt="Profile Photo">
  </div>

  <div class="info-group">
    <label>Full Name</label>
    <div class="value"><?= htmlspecialchars($user['name']) ?></div>
  </div>

  <div class="info-group">
    <label>Position</label>
    <div class="value"><?= htmlspecialchars($user['position']) ?></div>
  </div>

  <div class="info-group">
    <label>Phone</label>
    <div class="value"><?= htmlspecialchars($user['phone']) ?></div>
  </div>

  <div class="info-group">
    <label>Email</label>
    <div class="value"><?= htmlspecialchars($user['email']) ?></div>
  </div>
</div>

</body>
</html>

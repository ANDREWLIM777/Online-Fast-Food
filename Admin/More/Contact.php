<?php
require '../Admin_Account/db.php'; 
$sql = "SELECT photo, name, position, phone, email FROM admin WHERE role='superadmin'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Our Leadership</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
body {
  background: #0c0a10;
  font-family: 'Segoe UI', Tahoma, sans-serif;
  color: #f5f5dc;
  margin: 0;
  padding: 40px 20px;
  position: relative;
  z-index: 1;
}

/* 背景发光环 */
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
  z-index: -1; /* ⬅ 放底层 */
}

/* 星尘粒子 */
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
  z-index: -2; /* ⬅ 更底层 */
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

    .header {
  margin-bottom: 50px;
  text-align: center;
  position: relative;
}

.back-container {
  position: absolute;
  top: 0;
  left: 0;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: transparent;
  border: 2px solid #c0a23d;
  color: #c0a23d;
  padding: 10px 18px;
  border-radius: 30px;
  font-weight: bold;
  transition: all 0.3s ease;
  font-size: 0.95rem;
  text-decoration: none;
}

.back-btn i {
  transition: transform 0.3s ease;
}

.back-btn:hover {
  background: #c0a23d;
  color: #000;
}

.back-btn:hover i {
  transform: translateX(-5px);
}

/* 改良标题 */
.glow-title {
  font-size: 2.7rem;
  background: linear-gradient(90deg, #f6d365, #c0a23d, #b38f2d);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  font-family: 'Playfair Display', serif;
  letter-spacing: 3px;
  text-shadow: 0 0 10px rgba(255, 215, 0, 0.15);
  margin-top: 30px;
  margin-bottom: 0;
  padding-bottom: 10px;
  border-bottom: 2px solid #c0a23d20;
  display: inline-block;
}

    .grid {
        display: flex;
  flex-direction: column;
  gap: 30px;
  max-width: 700px;
  margin: auto;
    }

    .card {
      background:rgba(31, 31, 31, 0.68);
      border-radius: 16px;
      padding: 25px;
      text-align: center;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.35);
      transition: transform 0.3s ease;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 25px rgba(192, 162, 61, 0.25);
    }

    .card img {
  width: 150px;
  height: 150px;
  object-fit: cover;
  border-radius: 50%;
  border: 4px solid #c0a23d;
}

    .name {
      font-size: 2rem;
      font-weight: bold;
      color: #fff;
      margin-bottom: 5px;
    }

    .position {
      font-size: 1rem;
      color: #b5b5b5;
      margin-bottom: 15px;
      font-style: italic;
    }

    .contact-info {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .contact-info a {
      color: #c0a23d;
      text-decoration: none;
      font-size: 0.95rem;
      transition: all 0.2s ease;
    }

    .contact-info a:hover {
      text-shadow: 0 0 5px #c0a23d;
    }

    .contact-info i {
      margin-right: 8px;
    }

    @media (max-width: 600px) {
      .header h2 {
        font-size: 1.8rem;
      }
      .card img {
        width: 100px;
        height: 100px;
      }
    }
  </style>
</head>
<body>

<div class="header">
  <div class="back-container">
    <a href="more.php" class="back-btn">
      <i class="fas fa-arrow-left"></i><span> Back to More Page</span>
    </a>
  </div>
  <h2 class="glow-title">Brizo Senior Executive</h2>
</div>

<div class="grid">
  <?php while($row = $result->fetch_assoc()): ?>
    <div class="card">
      <img src="../Admin_Account/upload/<?= htmlspecialchars($row['photo']) ?>" alt="Leader Photo">
      <div class="name"><?= htmlspecialchars($row['name']) ?></div>
      <div class="position"><?= htmlspecialchars($row['position']) ?></div>
      <div class="contact-info">
        <a href="tel:<?= $row['phone'] ?>"><i class="fas fa-phone"></i><?= $row['phone'] ?></a>
        <a href="mailto:<?= $row['email'] ?>"><i class="fas fa-envelope"></i><?= $row['email'] ?></a>
      </div>
    </div>
  <?php endwhile; ?>
</div>

</body>
</html>

<?php
require 'db_connect.php';
include '../auth_menu.php';
check_permission('superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['photo']['tmp_name']);
        if (in_array($fileType, $allowedTypes)) {
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photoPath = $destination;
            } else $error = "Failed to upload photo.";
        } else $error = "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
    }

    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO menu_items 
                (category, item_name, description, price, is_available, photo)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['category'],
                htmlspecialchars($_POST['name']),
                htmlspecialchars($_POST['description']),
                filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                isset($_POST['is_available']) ? 1 : 0,
                $photoPath
            ]);
            header("Location: index.php?success=added");
            exit();
        } catch (PDOException $e) {
            $error = "Add failed: " . $e->getMessage();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $price = $_POST["price"];

    if ($price < 0) {
        $error = "Price cannot be negative.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>

@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap');

:root {
    --gold: #c0a23d;
    --gold-light: #e8d48b;
    --bg-dark: #0c0a10;
    --panel-dark: #181818;
    --text-light: #eee;
    --text-faint: #999;
    --input-dark: #252525;
}

body {
    font-family: 'Roboto', sans-serif;
    margin: 0;
    padding: 0;
    background: var(--bg-dark);
    color: var(--text-light);
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


header {
    background: linear-gradient(135deg, #000, #121212);
    padding: 1.0rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #444;
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.05);
    gap: 2rem;
}


header h1 {
    flex: 1;
    text-align: center;
    font-family: 'Playfair Display', serif;
    font-size: 2.3rem;
    background: linear-gradient(to right, var(--gold), var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;

}

a.btn, button.btn {
    background: linear-gradient(to left, var(--gold), var(--gold-light));
    color: #000;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 10px;
    font-weight: 500;
    font-family: 'Roboto', sans-serif;
    text-decoration: none;
    transition: all 0.2s ease-in-out;
}

a.btn:hover, button.btn:hover {
    background: var(--gold-light);
    box-shadow: 0 0 10px var(--gold-light);
    transform: scale(1.03);
}

main.content-wrapper {
    padding: 2rem;
    max-width: 850px;
    margin: auto;
}

h2 {
    text-align: center;
    font-family: 'Playfair Display', serif;
    font-size: 1.9rem;
    color: var(--gold);
    margin-bottom: 1.5rem;
}

.form-container {
    background: var(--panel-dark);
    padding: 2rem;
    border-radius: 14px;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.05);
    border: 1px solid #2e2e2e;
}

.form-group {
    margin-bottom: 1.4rem;
}

.form-group label {
    display: block;
    font-weight: 500;
    color: var(--gold-light);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

input[type="text"],
input[type="number"],
input[type="file"],
textarea,
select {
    width: 100%;
    padding: 10px;
    border: 1px solid #444;
    border-radius: 8px;
    background: var(--input-dark);
    color: #fff;
    font-size: 1rem;
}

textarea {
    resize: vertical;
}

.form-group input[type="checkbox"] {
    transform: scale(1.2);
    margin-right: 10px;
}

.form-actions {
    text-align: right;
    margin-top: 2rem;
}

.alert {
    padding: 1rem;
    background: #b88e14;
    color: #1b1b1b;
    font-weight: bold;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid #d3a800;
    background: linear-gradient(to right, #f5d77f, #e1c45f);
}

img {
    max-width: 100%;
    border-radius: 10px;
    border: 2px solid var(--gold-light);
    margin-top: 0.5rem;
}

        </style>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <title>Add New Item</title>
</head>
<body>
<header>
  <a href="index.php" class="btn">
    <i class="fas fa-chevron-left"></i>
    Back to Menu Page
  </a>
  <h1>Brizo Melaka Fast Food</h1>
  <div class="header-glow"></div>
  <div style="width: 135px;"></div>
</header>

    <main class="content-wrapper">
        <h2>Add New Menu Item</h2>

        <?php if(isset($error)): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>
                    <i class="fas fa-tag"></i>    
                    Category *</label>
                    <select name="category" required>
                        <option value="">-- Select --</option>
                        <option value="burger">Burger</option>
                        <option value="chicken">Chicken</option>
                        <option value="drink">Drink</option>
                        <option value="snacks">Snacks</option>
                        <option value="meal">Meal</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" step="0.01" name="price" min="0" required>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="is_available" checked> Available</label>
                </div>

                <div class="form-group">
                    <label>Upload Photo</label>
                    <input type="file" name="photo" accept="image/*" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Save Item</button>
                </div>
            </form>
        </div>
    </main>


</body>
</html>

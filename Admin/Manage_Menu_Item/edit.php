<?php
require_once 'db_connect.php';
include '../auth_menu.php';
check_permission('superadmin');

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $item = $stmt->fetch();
    
    if (!$item) {
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error loading item: " . $e->getMessage());
}

// Processing form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = null;
    $requiredFields = ['category', 'item_name', 'price'];
    
    // Server-side validation
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $error = "All fields marked with * must be completed!";
            break;
        }
    }
    
// Verify classification validity
    $allowedCategories = ['burger', 'chicken', 'drink', 'snacks', 'meal'];
    if (!isset($error) && !in_array($_POST['category'], $allowedCategories)) {
        $error = "Invalid categorization options";
    }
    
 // Validate the price format
    if (!isset($error) && !is_numeric($_POST['price'])) {
        $error = "Prices must be valid numbers";
    }

   // File upload processing
    $photoPath = $item['photo']; 
    if (!isset($error) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['photo']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            $error = "Only JPG, PNG, GIF formats are allowed.";
        } else {
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFileName;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $error = "Photo upload failed";
            } else {
                $photoPath = $destination;
            }
        }
    }

    // Database update
    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE menu_items SET
                category = :category,
                item_name = :name,
                description = :desc,
                price = :price,
                is_available = :available,
                promotion = :promotion,
                photo = :photo
                WHERE id = :id");
            
            $stmt->execute([
                ':category' => $_POST['category'],
                ':name' => htmlspecialchars($_POST['item_name']),
                ':desc' => htmlspecialchars($_POST['description'] ?? ''),
                ':price' => filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                ':available' => isset($_POST['is_available']) ? 1 : 0,
                ':promotion' => $_POST['promotion'] ?? null,
                ':photo' => $photoPath,
                ':id' => $_GET['id']
            ]);
            
            header("Location: index.php?success=updated");
            exit();
            
        } catch (PDOException $e) {
            $error = "Update Failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Item</title>
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
    background: linear-gradient(to right, var(--gold), var(--gold-light));
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
        <h2>Edit Menu Item</h2>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <?php
                        $cats = ['burger', 'chicken', 'drink', 'snacks', 'meal'];
                        foreach ($cats as $cat) {
                            $selected = $item['category'] === $cat ? 'selected' : '';
                            echo "<option value=\"$cat\" $selected>" . ucfirst($cat) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="item_name" value="<?= htmlspecialchars($item['item_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"><?= htmlspecialchars($item['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" step="0.01" name="price" value="<?= $item['price'] ?>" required>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="is_available" <?= $item['is_available'] ? 'checked' : '' ?>> Available</label>
                </div>

                <div class="form-group">
                    <label>Promotion Text</label>
                    <input type="text" name="promotion" value="<?= htmlspecialchars($item['promotion']) ?>">
                </div>

                <?php if (!empty($item['photo'])): ?>
                <div class="form-group">
                    <label>Current Photo</label>
                    <img src="<?= htmlspecialchars($item['photo']) ?>" alt="Current photo" style="max-width:200px;">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Upload New Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Update Item</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
<?php
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photoPath = null;
    // Handle file upload if provided
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['photo']['tmp_name']);
        if (in_array($fileType, $allowedTypes)) {
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photoPath = $destination;
            } else {
                $error = "Failed to upload photo.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
        }
    }

    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO menu_items 
                (item_name, description, price, is_available, promotion, photo)
                VALUES (?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                htmlspecialchars($_POST['name']),
                htmlspecialchars($_POST['description']),
                filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                isset($_POST['is_available']) ? 1 : 0,
                $_POST['promotion'] ?? null,
                $photoPath
            ]);
            
            header("Location: index.php?success=added");
            exit();
        } catch (PDOException $e) {
            $error = "Add failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Menu Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="content-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>Add New Item</h1>
                <a href="index.php" class="btn back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </header>

        <?php if(isset($error)): ?>
            <div class="error-alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post" enctype="multipart/form-data">
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
                    <input type="number" step="0.01" name="price" required>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_available" checked>
                        Available
                    </label>
                </div>

                <div class="form-group">
                    <label>Promotion Text</label>
                    <input type="text" name="promotion">
                </div>

                <div class="form-group">
                    <label>Upload Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn submit-btn">
                        <i class="fas fa-save"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- DO NOT MODIFY BELOW THIS POINT (Footer Navigation) -->
    <!-- [Footer navigation code remains unchanged] -->
</body>
</html>

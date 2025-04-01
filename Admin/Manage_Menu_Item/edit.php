<?php
require_once 'db_connect.php';

// Redirect if no ID is provided
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photoPath = null;
    // Handle file upload if a new photo is provided
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
            if ($photoPath !== null) {
                // Update including new photo
                $stmt = $pdo->prepare("UPDATE menu_items SET
                    item_name = :name,
                    description = :desc,
                    price = :price,
                    is_available = :available,
                    promotion = :promotion,
                    photo = :photo
                    WHERE id = :id");
                
                $stmt->execute([
                    ':name' => htmlspecialchars($_POST['item_name']),
                    ':desc' => htmlspecialchars($_POST['description']),
                    ':price' => filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    ':available' => isset($_POST['is_available']) ? 1 : 0,
                    ':promotion' => $_POST['promotion'] ?? null,
                    ':photo' => $photoPath,
                    ':id' => $_GET['id']
                ]);
            } else {
                // Update without changing the photo
                $stmt = $pdo->prepare("UPDATE menu_items SET
                    item_name = :name,
                    description = :desc,
                    price = :price,
                    is_available = :available,
                    promotion = :promotion
                    WHERE id = :id");
                
                $stmt->execute([
                    ':name' => htmlspecialchars($_POST['item_name']),
                    ':desc' => htmlspecialchars($_POST['description']),
                    ':price' => filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    ':available' => isset($_POST['is_available']) ? 1 : 0,
                    ':promotion' => $_POST['promotion'] ?? null,
                    ':id' => $_GET['id']
                ]);
            }
            
            header("Location: index.php?success=updated");
            exit();
        } catch (PDOException $e) {
            $error = "Error updating item: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Menu Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="content-wrapper">
        <div class="header-section">
            <h2>Edit Menu Item</h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" enctype="multipart/form-data" class="item-form">
                <div class="form-group">
                    <label for="item_name">Item Name *</label>
                    <input type="text" id="item_name" name="item_name" 
                           value="<?= htmlspecialchars($item['item_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"><?= 
                        htmlspecialchars($item['description']) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0"
                               value="<?= $item['price'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_available" 
                                <?= $item['is_available'] ? 'checked' : '' ?>>
                            <span class="checkmark"></span>
                            Available Now
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="promotion">Promotion Text</label>
                    <input type="text" id="promotion" name="promotion"
                           value="<?= htmlspecialchars($item['promotion']) ?>">
                </div>

                <!-- Display existing photo if available -->
                <?php if (!empty($item['photo'])): ?>
                <div class="form-group">
                    <label>Current Photo</label>
                    <img src="<?= htmlspecialchars($item['photo']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" style="max-width:200px;">
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Upload New Photo (optional)</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Item
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- DO NOT MODIFY BELOW THIS POINT (Footer Navigation) -->
    <!-- [Footer navigation code remains unchanged] -->
</body>
</html>

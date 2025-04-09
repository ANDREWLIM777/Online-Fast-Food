<?php
require 'db_connect.php';

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
                (category, item_name, description, price, is_available, promotion, photo)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['category'],
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
    <title>Add New Item</title>
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <header>
        <h1>Brizo Fast Food</h1>
        <a href="index.php" class="btn">Back</a>
    </header>

    <main class="content-wrapper">
        <h2>Add New Menu Item</h2>

        <?php if(isset($error)): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Category *</label>
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
                    <input type="number" step="0.01" name="price" required>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="is_available" checked> Available</label>
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
                    <button type="submit" class="btn">Save Item</button>
                </div>
            </form>
        </div>
    </main>


</body>
</html>

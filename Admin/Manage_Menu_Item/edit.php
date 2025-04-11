<?php
require_once 'db_connect.php';

// 重定向如果没有ID
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

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = null;
    $requiredFields = ['category', 'item_name', 'price'];
    
    // 服务器端验证
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $error = "All fields marked with * must be completed!";
            break;
        }
    }
    
    // 验证分类有效性
    $allowedCategories = ['burger', 'chicken', 'drink', 'snacks', 'meal'];
    if (!isset($error) && !in_array($_POST['category'], $allowedCategories)) {
        $error = "Invalid categorization options";
    }
    
    // 验证价格格式
    if (!isset($error) && !is_numeric($_POST['price'])) {
        $error = "Prices must be valid numbers";
    }

    // 文件上传处理
    $photoPath = $item['photo']; // 保留原有照片
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
                $photoPath = $destination; // 更新为新照片路径
            }
        }
    }

    // 数据库更新
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
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <header>
        <h1>Brizo Fast Food</h1>
        <a href="index.php" class="btn">Back</a>
    </header>

    <main class="content-wrapper">
        <h2>Edit Menu Item</h2>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post" enctype="multipart/form-data">
                <!-- 原有表单结构保持不变 -->
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
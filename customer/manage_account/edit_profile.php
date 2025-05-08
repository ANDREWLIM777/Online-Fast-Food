<?php
require '../db_connect.php';
session_start();

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header('Location: ../login.php');
    exit();
}

if (isset($_POST['delete_account'])) {
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    session_destroy();
    header("Location: ../register.php?deleted=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'] ?? null;
    $age = (int) $_POST['age'];
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
        // Upload image
    $photo = $_FILES['photo']['name'] ?? '';
    $upload_dir = '/Online-Fast-Food/Admin/Manage_Customer/upload/';
    $new_photo = '';
    $photoPath = '';


    if (!empty($_FILES['photo']['name'])) {
        $photoName = time() . '_' . basename($_FILES['photo']['name']);
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/Online-Fast-Food/Admin/Manage_Customer/upload/';
        $destination = $targetDir . $photoName;
    
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
    
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
            chmod($destination, 0644);
            $new_photo = $photoName;
        } else {
            $error = "❌ Failed to upload image.";
        }
    }

    $sql = "UPDATE customers SET fullname = ?, phone = ?, gender = ?, age = ?, address = ?, city = ?, postal_code = ?";
    if (!empty($new_photo)) {
        $sql .= ", photo = ?";
    }
    
    $sql .= " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($new_photo)) {
        $stmt->bind_param("sssissssi", $fullname, $phone, $gender, $age, $address, $city, $postal_code, $new_photo, $customerId);
    } else {
        $stmt->bind_param("sssisssi", $fullname, $phone, $gender, $age, $address, $city, $postal_code, $customerId);
    }
    

    if ($stmt->execute()) {
        header("Location: profile.php?updated=1");
        exit();
    } else {
        $error = "Failed to update profile.";
    }
}

$stmt = $conn->prepare("SELECT fullname, email, phone, gender, age, address, city, postal_code, photo FROM customers WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Profile - Brizo Fast Food Melaka</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="edit_profile.css">
</head>
<body>
  <div class="edit-container">
    <h2>Edit Your Profile</h2>
    <?php if (!empty($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <label for="fullname">Full Name</label>
      <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($customer['fullname']) ?>" required>

      <label for="phone">Phone</label>
      <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone']) ?>" required>

      <label for="gender">Gender</label>
      <select name="gender" id="gender">
        <option value="">Select</option>
        <option value="male" <?= $customer['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
        <option value="female" <?= $customer['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
      </select>

      <label for="age">Age</label>
      <input 
  type="number" 
  id="age" 
  name="age" 
  min="1" 
  max="100" 
  value="<?= htmlspecialchars($customer['age']) ?>" 
  required 
  title="Age must be between 1 and 100"
>

      <label for="address">Address</label>
      <textarea id="address" name="address"><?= htmlspecialchars($customer['address']) ?></textarea>

      <label for="city">City</label>
      <input type="text" id="city" name="city" value="<?= htmlspecialchars($customer['city']) ?>">

      <label for="postal_code">Postal Code</label>
      <input 
      type="text" 
      id="postal_code" 
      name="postal_code" 
      pattern="^\d{5}$" 
      maxlength="5"
      title="Postal code must be exactly 5 digits." 
      inputmode="numeric" 
      value="<?= htmlspecialchars($customer['postal_code']) ?>" 
      required
>

      <label for="photo">Profile Picture</label>
      <input type="file" id="photo" name="photo" accept="image/*">

      <button type="submit">Save Changes</button>
    </form>

    <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
      <input type="hidden" name="delete_account" value="1">
      <button type="submit" class="danger-btn">🗑️ Delete My Account</button>
    </form>
  </div>
</body>
</html>

<?php
require '../db_connect.php';
session_start();

// Check if user is logged in
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    header('Location: ../login.php');
    exit();
}

// Initialize error array and variables
$errors = [];
$photoPath = '';
$success = false;

// Fetch customer data
$stmt = $conn->prepare("SELECT fullname, email, phone, address, city, postal_code, photo FROM customers WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
if (!$customer) {
    header('Location: ../login.php');
    exit();
}

// Handle account deletion
if (isset($_POST['delete_account'])) {
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customerId);
    if ($stmt->execute()) {
        session_destroy();
        header("Location: ../register.php?deleted=1");
        exit();
    } else {
        $errors[] = "❌ Failed to delete account.";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {
    // Sanitize and validate inputs
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');

    // Validate Full Name (no numbers, at least 2 characters)
    if (strlen($fullname) < 2 || preg_match('/[0-9]/', $fullname)) {
        $errors[] = "❌ Full name must be at least 2 characters and contain no numbers.";
    }

    // Validate Phone (exactly 11 digits, no alpha)
    if (!preg_match('/^\d{11}$/', $phone)) {
        $errors[] = "❌ Phone number must be exactly 11 digits with no letters or special characters.";
    }

    // Validate Address (optional, but if provided, at least 5 characters)
    if (!empty($address) && strlen($address) < 5) {
        $errors[] = "❌ Address must be at least 5 characters if provided.";
    }

    // Validate City (optional, but if provided, no numbers)
    if (!empty($city) && preg_match('/[0-9]/', $city)) {
        $errors[] = "❌ City name must not contain numbers.";
    }

    // Validate Postal Code (exactly 5 digits)
    if (!preg_match('/^\d{5}$/', $postal_code)) {
        $errors[] = "❌ Postal code must be exactly 5 digits.";
    }

    // Handle file upload
    $new_photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        // Validate file type and size
        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $errors[] = "❌ Profile picture must be a JPEG, PNG, or GIF image.";
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $errors[] = "❌ Profile picture must be less than 2MB.";
        } else {
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
                $errors[] = "❌ Failed to upload image.";
            }
        }
    }

    // If no errors, update the database
    if (empty($errors)) {
        $sql = "UPDATE customers SET fullname = ?, phone = ?, address = ?, city = ?, postal_code = ?";
        if (!empty($new_photo)) {
            $sql .= ", photo = ?";
        }
        $sql .= " WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if (!empty($new_photo)) {
            $stmt->bind_param("ssssssi", $fullname, $phone, $address, $city, $postal_code, $new_photo, $customerId);
        } else {
            $stmt->bind_param("sssssi", $fullname, $phone, $address, $city, $postal_code, $customerId);
        }

        if ($stmt->execute()) {
            header("Location: profile.php?updated=1");
            exit();
        } else {
            $errors[] = "❌ Failed to update profile.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Profile - Brizo Fast Food Melaka</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="edit_profile.css">
  <style>
    .profile-pic-preview {
      max-width: 150px;
      margin-top: 10px;
      border-radius: 5px;
    }
    .current-photo {
      margin-top: 5px;
      font-size: 0.9em;
      color: #555;
    }
  </style>
</head>
<body>
  <div class="edit-container">
    <h2>Edit Your Profile</h2>
    <?php if (!empty($errors)): ?>
      <div style="color: red;">
        <?php foreach ($errors as $error): ?>
          <p><?= $error ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
      <label for="fullname">Full Name</label>
      <input 
        type="text" 
        id="fullname" 
        name="fullname" 
        value="<?= htmlspecialchars($_POST['fullname'] ?? $customer['fullname']) ?>" 
        pattern="[A-Za-z\s]+" 
        title="Full name must contain only letters and spaces." 
        required>

      <label for="phone">Phone</label>
      <input 
        type="tel" 
        id="phone" 
        name="phone" 
        value="<?= htmlspecialchars($_POST['phone'] ?? $customer['phone']) ?>" 
        pattern="\d{11}" 
        maxlength="11" 
        title="Phone number must be exactly 11 digits with no letters or special characters." 
        inputmode="numeric" 
        required>

      <label for="address">Address</label>
      <textarea 
        id="address" 
        name="address"><?= htmlspecialchars($_POST['address'] ?? $customer['address']) ?></textarea>

      <label for="city">City</label>
      <input 
        type="text" 
        id="city" 
        name="city" 
        value="<?= htmlspecialchars($_POST['city'] ?? $customer['city']) ?>" 
        pattern="[A-Za-z\s]+" 
        title="City name must contain only letters and spaces.">

      <label for="postal_code">Postal Code</label>
      <input 
        type="text" 
        id="postal_code" 
        name="postal_code" 
        pattern="^\d{5}$" 
        maxlength="5" 
        title="Postal code must be exactly 5 digits." 
        inputmode="numeric" 
        value="<?= htmlspecialchars($_POST['postal_code'] ?? $customer['postal_code']) ?>" 
        required>

      <label for="photo">Profile Picture</label>
      <input 
        type="file" 
        id="photo" 
        name="photo" 
        accept="image/jpeg,image/png,image/gif">
      <?php if (!empty($customer['photo'])): ?>
        
        <img 
          src="/Online-Fast-Food/Admin/Manage_Customer/upload/<?= htmlspecialchars($customer['photo']) ?>" 
          alt="Current Profile Picture" 
          class="profile-pic-preview">
      <?php else: ?>
        <div class="current-photo">No profile picture set.</div>
      <?php endif; ?>

      <button type="submit">Save Changes</button>
    </form>

    <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
      <input type="hidden" name="delete_account" value="1">
      <button type="submit" class="danger-btn">🗑️ Delete My Account</button>
    </form>
  </div>

  <script>
    function validateForm() {
      const phone = document.getElementById('phone').value;
      const phonePattern = /^\d{11}$/;
      if (!phonePattern.test(phone)) {
        alert('Phone number must be exactly 11 digits with no letters or special characters.');
        return false;
      }
      return true;
    }
  </script>
</body>
</html>

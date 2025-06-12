<!-- Admin/Manage_Account/update.php -->
<?php
require 'db_conn.php';
include '../auth_acc.php';
check_permission('superadmin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid access.');
}

$id       = intval($_POST['id']);
$name     = $_POST['name'];
$position = $_POST['position'];
$phone    = $_POST['phone'];
$role     = $_POST['role'];
$email    = $_POST['email'];
$password = $_POST['password'];

// Upload image
$photo = $_FILES['photo']['name'];
$upload_dir = '../Admin_Account/upload/';
$new_photo = '';

if (!empty($photo)) {
    $ext = pathinfo($photo, PATHINFO_EXTENSION);
    $new_photo = time() . '_' . basename($photo);
    move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_photo);
} else {
    $sql = "SELECT photo FROM admin WHERE id = $id";
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    $new_photo = $data['photo'];
}

if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $sql = "UPDATE admin 
            SET photo='$new_photo', name='$name', 
                position='$position', phone='$phone', role='$role', email='$email', password='$hashed'
            WHERE id=$id";
} else {
    $sql = "UPDATE admin 
            SET photo='$new_photo', name='$name', 
                position='$position', phone='$phone', role='$role', email='$email'
            WHERE id=$id";
}

$check = $conn->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
$check->bind_param("si", $email, $id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error'] = 'Email address is already in use by another admin.';
    header("Location: edit.php?id=" . $id);
    exit;
}

if (!empty($password)) {
    if (preg_match('/\s/', $password)) {
        $_SESSION['error'] = 'Password cannot contain spaces.';
        header("Location: edit.php?id=$id");
        exit;
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $sql = "UPDATE admin 
            SET photo='$new_photo', name='$name', 
                position='$position', phone='$phone', role='$role', email='$email', password='$hashed'
            WHERE id=$id";
}

// Check to see if any other administrators are using this phone number
$checkPhone = $conn->prepare("SELECT id FROM admin WHERE phone = ? AND id != ?");
$checkPhone->bind_param("si", $phone, $id);
$checkPhone->execute();
$resultPhone = $checkPhone->get_result();

if ($resultPhone->num_rows > 0) {
    $_SESSION['error'] = 'Phone number is already in use by another admin.';
    header("Location: edit.php?id=" . $id);
    exit;
}

if ($conn->query($sql)) {
    header("Location: index.php?msg=updated");
    exit;
} else {
    echo "Error updating record: " . $conn->error;
}
?>

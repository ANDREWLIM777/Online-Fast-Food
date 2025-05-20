<?php
require 'db_conn.php';
include '../auth_cus.php';
check_permission('superadmin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid access.');
}

$id = intval($_POST['id']);
$fullname = $_POST['fullname'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$city = $_POST['city'];
$postal = $_POST['postal_code'];
$address = $_POST['address'];
$password = $_POST['password'];

$photo = $_FILES['photo']['name'];
$upload_dir = 'upload/';
$new_photo = '';

if (!empty($photo)) {
    $ext = pathinfo($photo, PATHINFO_EXTENSION);
    $new_photo = time() . '_' . basename($photo);
    move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_photo);
} else {
    $res = $conn->query("SELECT photo FROM customers WHERE id = $id");
    $data = $res->fetch_assoc();
    $new_photo = $data['photo'];
}

if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $sql = "UPDATE customers SET fullname=?, email=?, phone=?, city=?, postal_code=?, address=?, password=?, photo=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssi", $fullname,  $email, $phone, $city, $postal, $address, $hashed, $new_photo, $id);
} else {
    $sql = "UPDATE customers SET fullname=?,  email=?, phone=?, city=?, postal_code=?, address=?, photo=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $fullname,  $email, $phone, $city, $postal, $address, $new_photo, $id);
}

if ($stmt->execute()) {
    header("Location: ../Manage_Customer/index.php?msg=updated");
    exit;
} else {
    echo "Update failed: " . $stmt->error;
}
?>

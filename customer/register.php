<?php
require '../db/db_connect.php';

$fullname = $_POST['fullname'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$phone = $_POST['phone'];

$stmt = $conn->prepare("INSERT INTO customers (fullname, email, password, phone) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $fullname, $email, $password, $phone);

if ($stmt->execute()) {
    echo "Registration successful! <a href='../login.html'>Login here</a>";
} else {
    echo "Error: " . $stmt->error;
}
?>

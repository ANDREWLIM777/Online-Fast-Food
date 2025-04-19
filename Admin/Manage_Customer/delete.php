<?php
require 'db_conn.php';
include '../auth_cus.php';
check_permission('superadmin');

if (!isset($_GET['id'])) {
    die('Missing ID');
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: index.php?msg=deleted");
    exit;
} else {
    echo "Failed to delete customer.";
}
?>

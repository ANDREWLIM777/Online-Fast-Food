<?php
include 'db_connect.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT * FROM menu");
$menu_items = array();

while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}

echo json_encode($menu_items);
?>
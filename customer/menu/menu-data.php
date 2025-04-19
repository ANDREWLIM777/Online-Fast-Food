<?php
// Fetch available items from database
$sql = "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY category, id";
$result = $conn->query($sql);

$menu_items = [];
while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}

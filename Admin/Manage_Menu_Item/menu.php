<?php
include 'db.php';

// Fetch all menu items
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $sql = "SELECT * FROM menu_items";
    $result = $conn->query($sql);
    $menu = [];

    while ($row = $result->fetch_assoc()) {
        $menu[] = $row;
    }
    echo json_encode($menu);
    exit();
}

// Add new item
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $price = $_POST["price"];
    $description = $_POST["description"];
    $available = $_POST["available"];
    $discount = $_POST["discount"];

    $sql = "INSERT INTO menu_items (name, price, description, available, discount) VALUES ('$name', '$price', '$description', '$available', '$discount')";
    if ($conn->query($sql) === TRUE) {
        echo "New item added successfully";
    } else {
        echo "Error: " . $conn->error;
    }
    exit();
}

// Update item
if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = $_PUT["id"];
    $name = $_PUT["name"];
    $price = $_PUT["price"];
    $description = $_PUT["description"];
    $available = $_PUT["available"];
    $discount = $_PUT["discount"];

    $sql = "UPDATE menu_items SET name='$name', price='$price', description='$description', available='$available', discount='$discount' WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        echo "Item updated successfully";
    } else {
        echo "Error: " . $conn->error;
    }
    exit();
}

// Delete item
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = $_DELETE["id"];

    $sql = "DELETE FROM menu_items WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        echo "Item deleted successfully";
    } else {
        echo "Error: " . $conn->error;
    }
    exit();
}

$conn->close();
?>
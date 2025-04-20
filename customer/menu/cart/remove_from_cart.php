<?php
session_start();

if (isset($_POST['item_id'])) {
    $id = (int) $_POST['item_id'];
    unset($_SESSION['cart'][$id]);
}

header("Location: cart.php");
exit;

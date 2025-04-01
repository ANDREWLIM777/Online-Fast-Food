<?php
// delete.php
require 'db_connect.php';

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        header("Location: index.php?success=deleted");
        exit();
    } catch (PDOException $e) {
        error_log("Delete failed: " . $e->getMessage());
        header("Location: index.php?error=delete_failed");
        exit();
    }
}
header("Location: index.php");
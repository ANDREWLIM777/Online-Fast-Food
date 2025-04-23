<?php
require 'db_conn.php';
include '../../auth_notifications.php';
check_permission('superadmin');

$id = $_GET['id'] ?? null;
$action = $_GET['act'] ?? null;

if ($id && in_array($action, ['pin', 'unpin'])) {
    $isPinned = ($action === 'pin') ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE notifications SET is_pinned = ? WHERE id = ?");
    $stmt->execute([$isPinned, $id]);
}
header("Location: index.php");
exit();
?>

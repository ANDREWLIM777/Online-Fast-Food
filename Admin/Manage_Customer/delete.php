<?php
require 'db_conn.php';
include '../auth_cus.php';
check_permission('superadmin');

if (session_status() === PHP_SESSION_NONE) session_start();

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("SELECT fullname, email FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    echo "Customer not found.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: index.php?msg=deleted");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Delete Customer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f0f0f;
            color: #eee;
            padding: 2rem;
        }

        .container {
            background: #1c1c1c;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            margin: auto;
        }

        h2 {
            color: #e57373;
            text-align: center;
        }

        .btn-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 2rem;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            border: none;
        }

        .btn-cancel {
            background: #444;
            color: #eee;
        }

        .btn-cancel:hover {
            background: #666;
        }

        .btn-delete {
            background: #ff4d4d;
            color: white;
        }

        .btn-delete:hover {
            background: #ff1a1a;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 1.5rem;
            background: none;
            color: #e8d48b;
            border: 1px solid #c0a23d;
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #c0a23d;
            color: #000;
        }
    </style>
</head>
<body>

<a class="back-btn" href="index.php"><i class="fas fa-arrow-left"></i> Back to Customers</a>

<div class="container">
    <h2>🗑️ Confirm Delete</h2>
    <p style="text-align: center;">
        Are you sure you want to delete<br>
        <strong><?= htmlspecialchars($customer['fullname']) ?> (<?= htmlspecialchars($customer['email']) ?>)</strong>?
    </p>

    <form method="POST">
        <div class="btn-group">
            <a href="index.php" class="btn btn-cancel">Cancel</a>
            <button type="submit" class="btn btn-delete">Delete</button>
        </div>
    </form>
</div>

</body>
</html>

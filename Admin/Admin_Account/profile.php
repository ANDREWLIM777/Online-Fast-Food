<?php
include '../auth.php';
include 'db.php';
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM admin WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $age = $_POST['age'];
    $phone = $_POST['phone'];

    if ($_FILES['photo']['name']) {
        $photo = time() . '_' . $_FILES['photo']['name'];
        move_uploaded_file($_FILES['photo']['tmp_name'], "upload/" . $photo);
        $update = $conn->prepare("UPDATE admin SET age=?, phone=?, photo=? WHERE id=?");
        $update->bind_param("issi", $age, $phone, $photo, $user_id);
    } else {
        $update = $conn->prepare("UPDATE admin SET age=?, phone=? WHERE id=?");
        $update->bind_param("isi", $age, $phone, $user_id);
    }

    if ($update->execute()) {
        echo "<script>alert('Profile updated.'); window.location.href='../Main Page/main_page.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Edit Profile</h2>
    <form method="POST" enctype="multipart/form-data">
        <label>Full Name</label>
        <input type="text" value="<?php echo $user['name']; ?>" disabled>

        <label>Email</label>
        <input type="text" value="<?php echo $user['email']; ?>" disabled>

        <label>Role</label>
        <input type="text" value="<?php echo $user['role']; ?>" disabled>

        <label>Age</label>
        <input type="number" name="age" value="<?php echo $user['age']; ?>" required>

        <label>Phone</label>
        <input type="text" name="phone" value="<?php echo $user['phone']; ?>" required>

        <label>Change Photo</label>
        <input type="file" name="photo" accept="image/*">

        <button type="submit">Update</button>
    </form>
</div>
</body>
</html>

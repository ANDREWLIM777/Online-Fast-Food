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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">
   
<head>

<style>

.form-back-btn {
    position: absolute;
    left: 15px;
    top: 15px;
    background: linear-gradient(45deg, #0c0a10, #1a1a1a);
    border: 2px solid #c0a23d;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(192, 162, 61, 0.2);
    z-index: 100;
        }

.form-back-btn i {
    color: #c0a23d;
    font-size: 1.4rem;
    margin-right: 2px;
    transition: transform 0.3s ease;
}

.form-back-btn:hover {
    transform: translateX(-3px) scale(1.05);
    border-color: #907722;
    box-shadow: 0 6px 20px rgba(144, 119, 34, 0.3);
}

.form-back-btn:hover i {
    transform: translateX(-2px);
    color: #907722;
}
/* 调整表单容器定位 */
.form-container {
    position: relative; /* 为绝对定位按钮建立基准 */
    margin-top: 0px; /* 为按钮留出空间 */
    padding-top: 20px; /* 防止内容被遮挡 */
}

</style>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
<div class="form-container">
    <a href="../Main Page/main_page.php" class="form-back-btn">
        <i class="fas fa-chevron-left"></i>
    </a>
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

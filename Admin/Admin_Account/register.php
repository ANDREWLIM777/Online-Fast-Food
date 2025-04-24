<?php
/* 注册处理逻辑 */
include 'db.php';
include '../auth.php';
check_permission('superadmin');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];
    $position = $_POST['position'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // 验证 @brizo.com 邮箱
    if (!str_ends_with($email, '@brizo.com')) {
        echo "<script>alert('Only @brizo.com email allowed'); window.history.back();</script>";
        exit();
    }

    // 处理照片上传
    $photo = "default.jpg";
    if (!empty($_FILES['photo']['name'])) {
        $photo = time() . '_' . basename($_FILES["photo"]["name"]);
        $target_file = "upload/" . $photo;
        move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file);
    }

    // 插入数据库
    $stmt = $conn->prepare("INSERT INTO admin (name, gender, age, position, phone, role, email, password, photo) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissssss", $name, $gender, $age, $position, $phone, $role, $email, $password, $photo);

    if ($stmt->execute()) {
        echo "<script>alert('Registration Successful'); window.location.href='../Main Page/main_page.php';</script>";
    } else {
        echo "<script>alert('Registration Failed: " . $conn->error . "');</script>";
    }
}
?>

 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Brizo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">

  <style>
    
        body {
            font-family: 'Roboto', sans-serif;
            background: #0c0a10;
            color: #eee;
            margin: 0;
            padding-top: 40px;
        }

        body::after {
    content: '';
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 50% 50%, 
        rgba(244, 227, 178, 0.07) 0%, 
        transparent 70%);
    animation: auraPulse 8s infinite;
    pointer-events: none;
    z-index: 0;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(244, 228, 178, 0.15) 1px, transparent 2px),
        radial-gradient(circle at 80% 70%, rgba(244, 228, 178, 0.15) 1px, transparent 2px);
    background-size: 60px 60px;
    animation: stardust 20s linear infinite;
    z-index: 0;
}

@keyframes auraPulse {
    0% { transform: scale(0.8); opacity: 0.3; }
    50% { transform: scale(1.2); opacity: 0.08; }
    100% { transform: scale(0.8); opacity: 0.3; }
}

@keyframes stardust {
    0% { background-position: 0 0, 100px 100px; }
    100% { background-position: 100px 100px, 0 0; }
}
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
    text-decoration: none !important;
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
    margin-top: 40px; /* 为按钮留出空间 */
    padding-top: 50px; /* 防止内容被遮挡 */
}

        .form-container {
            max-width: 500px;
            margin: auto;
            background: #1a1a1a;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 25px #00000080;
        }

        h2 {
            text-align: center;
            background: linear-gradient(45deg, #c0a23d,rgb(208, 171, 50));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Playfair Display', serif;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: 500;
            color: #c8b071;
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 8px;
            border: none;
            background: #262626;
            color: #fff;
        }

        input[type="file"] {
            background: none;
        }

        button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right,rgb(192, 175, 61),rgb(230, 189, 55));
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            color: #000;
            cursor: pointer;
        }

        button:hover {
            background: #b49836;
        }

       
    </style>
</head>
<body>
<div class="header">

    <div class="form-container">
    <a href="../Main Page/main_page.php" class="form-back-btn">
        <i class="fas fa-chevron-left"></i>
    </a>
    
        <h2>Register New Administration</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Upload Photo</label>
            <input type="file" name="photo" accept="image/*">

            <label>Full Name</label>
            <input type="text" name="name" required>

            <label>Gender</label>
            <select name="gender" required>
                <option value="">-- Select --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>

            <label>Age</label>
            <input type="number" name="age" required>

            <label>Position</label>
            <input type="text" name="position" required>

            <label>Phone</label>
            <input type="text" name="phone" required>

            <label>Role</label>
            <select name="role" required>
                <option value="admin">Admin</option>
                <option value="superadmin">Superadmin</option>
            </select>

            <label>Email (@brizo.com)</label>
            <input type="email" name="email" required>

            <label>Password</label>
<div style="position: relative;">
    <input type="password" name="password" id="passwordField" required style="padding-right: 10px;">
    <span onclick="togglePassword()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #c0a23d;">
        <i id="eyeIcon" class="fas fa-eye-slash"></i>
    </span>
</div>

            <button type="submit">Register</button>
        </form>
    </div>
    <script>
function togglePassword() {
    const passwordInput = document.getElementById("passwordField");
    const eyeIcon = document.getElementById("eyeIcon");
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        eyeIcon.classList.remove("fa-eye-slash");
        eyeIcon.classList.add("fa-eye");
    } else {
        passwordInput.type = "password";
        eyeIcon.classList.remove("fa-eye");
        eyeIcon.classList.add("fa-eye-slash");
    }
}
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        .menu-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        .menu-icon {
            cursor: pointer;
            width: 30px;
            height: 24px;
            position: relative;
            transition: all 0.3s ease;
        }

        .menu-icon span {
            position: absolute;
            height: 3px;
            width: 100%;
            background:rgb(192, 168, 61);
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .menu-icon span:nth-child(1) { top: 0; }
        .menu-icon span:nth-child(2) { top: 10px; }
        .menu-icon span:nth-child(3) { top: 20px; }

        .menu-icon:hover span {
            background: #eace7c; /* 悬停亮金色 */
        }

        .menu-icon.active span {
            background: #c0a23d;
            box-shadow: 0 0 8px rgba(192,162,61,0.3);
        }

        .menu-icon.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .menu-icon.active span:nth-child(2) { opacity: 0; }

        .menu-icon.active span:nth-child(3) {
            transform: rotate(-45deg) translate(8px, -8px);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 40px;
            left: 0;
            background: #0c0a10; /* 深黑背景 */
            border: 1px solid rgba(192, 162, 61, 0.2); /* 金色边框 */
            border-radius: 6px;
            padding: 8px 0;
            box-shadow: 0 4px 20px rgba(192, 162, 61, 0.1); /* 金色阴影 */
            backdrop-filter: blur(8px); /* 毛玻璃效果 */
        }

        .dropdown-menu.active {
            display: block;
            animation: slideDown 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 24px;
            text-decoration: none;
            color: #c0a23d; /* 主金色 */
            font-size: 0.95rem;
            transition: all 0.25s ease;
            position: relative;
        }

        .dropdown-menu a:hover {
            background: rgba(192, 162, 61, 0.1); /* 淡金背景 */
            color: #f4e3b2; /* 亮金色 */
            padding-left: 28px;
            text-shadow: 0 0 8px rgba(244, 227, 178, 0.3);
        }

        .dropdown-menu a:hover::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background: #f4e3b2;
            border-radius: 50%;
        }

        @keyframes slideDown {
            from { 
                opacity: 0; 
                transform: translateY(-15px) rotateX(-15deg);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) rotateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="menu-container">
        <div class="menu-icon" onclick="toggleMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <nav class="dropdown-menu">
            <a href="../Main Page/main_page.php">Home</a>
            <a href="#services">Services</a>
            <a href="../More/Contact.php">Contact</a>
            <a href="../MOre/notifications/index.php">Notifications</a>
        </nav>
    </div>

    <script>
        function toggleMenu() {
            const icon = document.querySelector('.menu-icon');
            const menu = document.querySelector('.dropdown-menu');
            
            icon.classList.toggle('active');
            menu.classList.toggle('active');
        }
    </script>
</body>
</html>

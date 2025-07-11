<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (preg_match('/\s/', $password)) {
    $_SESSION['login_error'] = "Password cannot contain spaces.";
    header("Location: login.php");
    exit();
}
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: ../Main Page/main_page.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Wrong password.";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Account not found.";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        body {
            margin: 0;
            height: 100vh;
            /* 主内容层 */
            position: relative;
        }


        .container {
    width: 600px;
    position: fixed;
    top: 100px; /* 控制往下移动 */
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
    z-index: 1000;
}
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            
            /* 背景设置 */
            background: url('brizo_logo.jpg') repeat;
            opacity: 0.15; /* 透明度调节 */
            
            /* 防止Logo被拉伸 */
            background-size: 200px; /* 根据实际Logo尺寸调整 */
            background-repeat: repeat;
        }

        /* 内容容器样式 */
        .content {
            padding: 20px;
            color: #333; /* 确保文字可读性 */
        }

        .top-error-box {
    position: fixed;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    background: #ff4d4d;
    color: white;
    padding: 15px 25px;
    border-radius: 0 0 12px 12px;
    font-weight: bold;
    text-align: center;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(255, 0, 0, 0.2);
    opacity: 1;
    transition: opacity 1s ease-out;
    max-width: 600px;
    width: 90%;
}

.top-error-box.fade-out {
    opacity: 0;
    pointer-events: none;
}



    </style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <meta charset="UTF-8">
    <title>Login - Brizo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php session_start(); ?>
<?php if (isset($_SESSION['login_error'])): ?>
    <div class="top-error-box" id="errorBox"><?= htmlspecialchars($_SESSION['login_error']) ?></div>
    <?php unset($_SESSION['login_error']); ?>
<?php endif; ?>


<div class="container">
    <h2>Login</h2>
    <form method="POST">
        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
<div style="position: relative;">
    <input type="password" name="password" id="passwordField" required
       style="padding-right: 10px;"
       onkeydown="return event.key !== ' '"
       oninput="this.value = this.value.replace(/\s/g, '')"
       onpaste="return false">
    <span onclick="togglePassword()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #c0a23d;">
        <i id="eyeIcon" class="fas fa-eye-slash"></i>
    </span>
</div>

        <button type="submit">Login</button>

    </form>
    <div style="text-align: left; margin-top: 20px;">
  <a href="forgot_password.php" style="color: #c0a23d; text-decoration: underline; font-size: 0.95rem;">Forgot password?</a>
</div>
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


window.onload = function () {
    const errorBox = document.getElementById('errorBox');
    if (errorBox) {
        setTimeout(() => {
            errorBox.classList.add('fade-out');
        }, 5000);
    }
};




</script>

</body>
</html>

<!DOCTYPE html>
<html lang="en">
    <head>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">


<style>

.menu-btn {
                position: absolute;
                left: 2rem;
                top: 50%;
                transform: translateY(-50%);
                background: linear-gradient(135deg, #c0a23d, #e8d48b);
                color: #000;
                padding: 12px 25px;
                border-radius: 30px;
                text-decoration: none;
                font-family: 'Roboto', sans-serif;
                font-weight: 600;
                font-size: 1.05rem;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 4px 15px rgba(192, 162, 61, 0.3);
                border: 1px solid #e8d48b;
            }

            .menu-btn:hover {
                transform: translateY(-50%) scale(1.05);
                box-shadow: 0 6px 25px rgba(192, 162, 61, 0.4);
            }

            .menu-btn:active {
                transform: translateY(-50%) scale(0.95);
            }

            .menu-btn i {
                font-size: 1.2rem;
            }

/* 黄金比例艺术标题 */
.header {
    left: 0;
    right: 0;   
    position: fixed;
    top: 0;
    width: 100%;
    background: 
        linear-gradient(135deg, #000000 0%, #0c0a10 100%),
        repeating-linear-gradient(-30deg, 
            transparent 0px 10px, 
            #f4e3b215 10px 12px,
            transparent 12px 22px);
    padding: 1.8rem 0;
    box-shadow: 0 4px 25px rgba(0,0,0,0.06);
    z-index: 999;
    display: flex;
    justify-content: center;
    border-bottom: 1px solid #eee3c975;
    overflow: hidden;
}

.title-group {
    position: relative;
    text-align: center;
    padding: 0 2.5rem;
}

.main-title {
    font-size: 2.1rem;/* 中间尺寸 */
    background: linear-gradient(45deg, #c0a23d, #907722);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-family: 'Playfair Display', serif;
    letter-spacing: 1.8px;
    line-height: 1.15;
    text-shadow: 1px 1px 2px rgba(255,255,255,0.3);
    margin-bottom: 0.4rem;
    transition: all 0.3s ease;
}

.sub-title {
    font-size: 1.05rem;
    color: #907722;
    font-family: 'Roboto', sans-serif;
    font-weight: 400;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    opacity: 0.9;
    position: relative;
    display: inline-block;
    padding: 0 15px;
}

/* 双装饰线动画 */
.sub-title::before,
.sub-title::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 35px;
    height: 1.2px;
    background: linear-gradient(90deg, #c9a227aa, transparent);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.sub-title::before {
    left: -30px;
    transform: translateY(-50%) rotate(-15deg);
}

.sub-title::after {
    right: -30px;
    transform: translateY(-50%) rotate(15deg);
}

.title-group:hover .sub-title::before {
    left: -35px;
    width: 35px;
}

.title-group:hover .sub-title::after {
    right: -35px;
    width: 35px;
}

/* 动态光晕背景 */
.header::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 50% 50%, 
        #f4e3b210 0%, 
        transparent 60%);
    animation: auraPulse 8s infinite;
    pointer-events: none;
}

@keyframes auraPulse {
    0% { transform: scale(0.8); opacity: 0.3; }
    50% { transform: scale(1.2); opacity: 0.1; }
    100% { transform: scale(0.8); opacity: 0.3; }
}

/* 微光粒子 */
.header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 30%, #f4e4b239 1px, transparent 2px),
        radial-gradient(circle at 80% 70%, #f4e4b236 1px, transparent 2px);
    background-size: 40px 40px;
    animation: stardust 20s linear infinite;
}

@keyframes stardust {
    0% { background-position: 0 0, 100px 100px; }
    100% { background-position: 100px 100px, 0 0; }
}
</style>

    </head>

    <body>

        <div class="header">

        <a href="/Online-Fast-Food/customer/menu/menu.php" class="menu-btn">
                <i class="fas fa-utensils"></i>
                Back to Customer Page
</a>

                <div class="title-group">
                    <div class="main-title">BRIZO MELAKA</div>
                    <div class="sub-title">Administration Login Page</div>
                </div>
            </div>
        
    </body>
</html>

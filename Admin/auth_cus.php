<?php
/* 权限控制模块（可 include 到所有页面使用） */

session_start();

// 判断是否登录
if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_role'])) {
    header("Location: ../Admin_Account/login.php");
    exit();
}

// 判断权限等级函数
function check_permission($role_required) {
    if ($_SESSION['user_role'] !== $role_required) {
        echo "<script>alert('Access Denied: Only $role_required allowed.'); window.location.href='../Manage_Customer/index.php';</script>";
        exit();
    }
}
?>

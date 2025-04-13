<?php
/* MySQL 数据库连接配置 */

$host = 'localhost';
$user = 'root';         // 改成你的数据库用户名
$pass = '';             // 改成你的数据库密码
$db   = 'brizo';        // 已设定数据库名

$conn = new mysqli($host, $user, $pass, $db);

// 检查连接
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
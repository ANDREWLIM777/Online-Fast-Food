<?php
session_start();

// Clear previous session just in case
session_unset();
session_destroy();

// Start fresh session
session_start();

// ✅ Set guest flag
$_SESSION['is_guest'] = true;

// Optionally: Give guest a random temporary name
$_SESSION['customer_name'] = "Guest_" . substr(md5(rand()), 0, 6);

// ✅ Redirect to menu page
header("Location: /Online-Fast-Food/customer/menu/menu.php");
exit();
?>

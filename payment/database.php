<?php
$host = "localhost"; // Change if using a remote database
$user = "root"; // Your database username
$pass = ""; // Your database password
$dbname = "brizo_fastfood"; // Your database name

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Database Connected Successfully!";
?>

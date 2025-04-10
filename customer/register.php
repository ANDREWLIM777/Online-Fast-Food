<?php
include 'db_connect.php';

// Get raw JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Sanitize inputs
$full_name = trim($conn->real_escape_string($data['full_name']));
$email = trim($conn->real_escape_string($data['email']));
$phone = trim($conn->real_escape_string($data['phone']));
$password = $data['password'];

// Validate inputs
if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
    die(json_encode(['success' => false, 'error' => 'All fields are required']));
}

// Check if email exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die(json_encode(['success' => false, 'error' => 'Email already registered']));
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>

<?php
include 'db_connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    die(json_encode(['success' => false, 'error' => 'Invalid JSON data']));
}

// Add debug logging
file_put_contents('debug.log', print_r($data, true), FILE_APPEND);

// Rest of your existing code...

// After execute()
file_put_contents('debug.log', "Insert result: " . print_r($stmt->errorInfo(), true), FILE_APPEND);
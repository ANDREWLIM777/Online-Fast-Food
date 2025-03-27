<?php
$host = "localhost";  // Change if using a remote database
$user = "root";       // Your MySQL username
$pass = "";           // Your MySQL password
$dbname = "brizo_fastfood"; // Your database name

// Create a database connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = $_POST["full_name"];
    $cardNumber = $_POST["card_number"];
    $expiryDate = $_POST["expiry_date"];
    $cvv = $_POST["cvv"];

    // Prevent SQL Injection
    $fullName = $conn->real_escape_string($fullName);
    $cardNumber = $conn->real_escape_string($cardNumber);
    $expiryDate = $conn->real_escape_string($expiryDate);
    $cvv = $conn->real_escape_string($cvv);

    // Insert data into database
    $sql = "INSERT INTO payments (full_name, card_number, expiry_date, cvv) 
            VALUES ('$fullName', '$cardNumber', '$expiryDate', '$cvv')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Payment Successful!');</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="payment-container">
        <h2>Payment Details</h2>
        <form action="" method="POST">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" placeholder="Enter your name" required>

            <label for="card_number">Card Number</label>
            <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>

            <label for="expiry_date">Expiry Date</label>
            <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>

            <label for="cvv">CVV</label>
            <input type="text" id="cvv" name="cvv" placeholder="123" required>

            <button type="submit">Submit Payment</button>
        </form>
    </div>

</body>
</html>

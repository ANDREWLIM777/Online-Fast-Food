<?php
// expense_input.php
require 'db_conn.php';
session_start();

$current_month = date('Y-m');
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'month_year' => $_POST['month'],
        'electricity' => $_POST['electricity'],
        'water' => $_POST['water'],
        // ... get all other fields
    ];
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM monthly_expenses 
                          WHERE month_year = ?");
    $stmt->execute([$data['month_year']]);
    
    if($stmt->fetch()) {
        // Update existing
        $sql = "UPDATE monthly_expenses SET electricity=?, water=?, ... 
               WHERE month_year=?";
    } else {
        // Insert new
        $sql = "INSERT INTO monthly_expenses (...) VALUES (...)";
    }
    
    $pdo->prepare($sql)->execute(array_values($data));
    header("Location: analysis_report.php?month={$data['month_year']}");
    exit;
}

// Get existing data
$stmt = $pdo->prepare("SELECT * FROM monthly_expenses 
                      WHERE month_year = ?");
$stmt->execute([$selected_month]);
$current_data = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<!-- Similar styling as analysis report -->
<body>
    <form method="post">
        <label>Month: 
            <input type="month" name="month" 
                   max="<?= $current_month ?>" 
                   value="<?= $selected_month ?>">
        </label>
        
        <label>Electricity: 
            <input type="number" name="electricity" 
                   value="<?= $current_data['electricity'] ?? 0 ?>" step="0.01">
        </label>
        
        <!-- Add all other expense fields -->
        
        <button type="submit">Save Expenses</button>
    </form>
</body>
</html>
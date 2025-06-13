<?php
// expense_input.php
require 'db_conn.php';
session_start();

$current_month = date('Y-m');
$selected_month = $_GET['month'] ?? $current_month;

// Get existing expense record
$stmt = $pdo->prepare("SELECT * FROM monthly_expenses WHERE month_year = ?");
$stmt->execute([$selected_month]);
$current_data = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tax_percentage = max(0, min(100, floatval($_POST['tax'])));
    $month = $_POST['month'];

    //  Income from orders table based on created_at
    $stmt = $pdo->prepare("SELECT SUM(total) FROM orders 
                           WHERE status = 'completed' 
                           AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthly_income = floatval($stmt->fetchColumn()) ?? 0;
    $current_data = $stmt->fetch();

    //  Tax amount based on income
    $tax_amount = $monthly_income * ($tax_percentage / 100);

    //  Form data preparation
    $data = [
        'month_year'       => $month,
        'electricity'      => max(0, floatval($_POST['electricity'])),
        'water'            => max(0, floatval($_POST['water'])),
        'salaries'         => max(0, floatval($_POST['salaries'])),
        'rent'             => max(0, floatval($_POST['rent'])),
        'cost'             => max(0, floatval($_POST['cost'])),
        'tax'              => $tax_amount,
        'tax_percentage'   => $tax_percentage,
        'other'            => max(0, floatval($_POST['other'])),
    ];

    //  Update or insert record
    $stmt = $pdo->prepare("SELECT id FROM monthly_expenses WHERE month_year = ?");
    $stmt->execute([$data['month_year']]);

    if ($stmt->fetch()) {
        $sql = "UPDATE monthly_expenses SET 
                    electricity = ?, water = ?, salaries = ?, 
                    rent = ?, cost = ?, tax = ?, tax_percentage = ?, other = ? 
                WHERE month_year = ?";
        $pdo->prepare($sql)->execute([
            $data['electricity'], $data['water'], $data['salaries'],
            $data['rent'], $data['cost'], $data['tax'], $data['tax_percentage'],
            $data['other'], $data['month_year']
        ]);
    } else {
        $sql = "INSERT INTO monthly_expenses 
                (month_year, electricity, water, salaries, rent, cost, tax, tax_percentage, other) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute(array_values($data));
    }

    header("Location: analysis_report.php?month={$data['month_year']}");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Monthly Expenses Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <style>
    :root {
        --gold: #c0a23d;
        --gold-light: #e8d48b;
        --dark-bg: #1a1a1a;
        --input-bg: #2d2d2d;
        --error: #ff6b6b;
    }

    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: var(--dark-bg);
        color: var(--gold-light);
        padding: 2rem;
        min-height: 100vh;
    }

    body::after {
        content: '';
        position: fixed;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle at 50% 50%, rgba(244, 227, 178, 0.07) 0%, transparent 70%);
        animation: auraPulse 8s infinite;
        pointer-events: none;
        z-index: -1;
    }

    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(244, 228, 178, 0.15) 1px, transparent 2px),
            radial-gradient(circle at 80% 70%, rgba(244, 228, 178, 0.15) 1px, transparent 2px);
        background-size: 60px 60px;
        animation: stardust 20s linear infinite;
        pointer-events: none;
        z-index: -2;
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

    .back-btn {
        display: inline-block;
        background: linear-gradient(to right, var(--gold), var(--gold-light));
        color: #000;
        font-weight: bold;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        transition: all 0.3s ease;
        margin-bottom: 2rem;
        box-shadow: 0 2px 10px rgba(192, 162, 61, 0.3);
    }

    .back-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(192, 162, 61, 0.5);
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
        background: linear-gradient(145deg, #1e1e1e, #2a2a2a);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        border: 1px solid rgba(192, 162, 61, 0.2);
        position: relative;
        overflow: hidden;
    }

    .container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--gold), var(--gold-light));
    }

    h1.glow-title {
        text-align: center;
        margin: 0 0 2rem;
        background: linear-gradient(90deg, #f6d365, var(--gold), #b38f2d);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-size: 2.5rem;
        font-family: 'Playfair Display', serif;
        letter-spacing: 0.05em;
        text-shadow: 0 2px 10px rgba(192, 162, 61, 0.3);
    }

    .input-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }

    .input-group {
        position: relative;
        margin-bottom: 1rem;
    }

    .input-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--gold-light);
        font-weight: 500;
        font-size: 1rem;
        letter-spacing: 0.03em;
    }

    .input-group input {
    width: 100%;
    padding: 12px 15px;
    background: var(--input-bg);
    border: 2px solid rgba(192, 162, 61, 0.3);
    border-radius: 8px;
    color: #fff;
    font-size: 1.05rem;
    transition: all 0.3s ease;
    font-family: 'Roboto', sans-serif;
}

.currency-input {
    position: relative;
}

.currency-input input {
    width: 100%;
    padding: 12px 15px;
    padding-left: 0; 
    text-indent: 45px; 
    background: var(--input-bg);
    border: 2px solid rgba(192, 162, 61, 0.3);
    border-radius: 8px;
    color: #fff;
    font-size: 1.05rem;
    transition: all 0.3s ease;
    font-family: 'Roboto', sans-serif;
}

.currency-input::before {
    content: 'RM';
    position: absolute;
    left: 15px;
    bottom: 12px;
    color: rgba(192, 162, 61, 0.8);
    font-weight: 700;
    font-size: 1.1rem;
    pointer-events: none;
}


    .percentage-input input {
        padding-right: 0px;
        text-align: left;
       font-family: 'Roboto', sans-serif;
        font-weight: 500;
    }

    .percentage-input::after {
        content: '%';
        position: absolute;
        right: 15px;
        bottom: 12px;
        color: rgba(192, 162, 61, 0.8);
        font-weight: 700;
        font-size: 1.1rem;
    }


    .input-group input:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(192, 162, 61, 0.2);
    }

    .month-selector {
        margin-bottom: 2rem;
    }

    .month-selector label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--gold-light);
    }

    .month-selector input {
        width: 100%;
        padding: 12px;
        background: var(--input-bg);
        border: 2px solid rgba(192, 162, 61, 0.3);
        border-radius: 8px;
        font-family: 'Roboto', sans-serif;
        font-size: 1rem;
        color: #fff;
    }

    .total-section {
        margin: 2.5rem 0;
        padding: 1.5rem;
        background: rgba(192, 162, 61, 0.1);
        border-radius: 8px;
        border-left: 4px solid var(--gold);
    }

    .total-section span {
        display: block;
        margin-bottom: -1rem;
        font-family: 'Roboto', sans-serif;
        font-size: 1.5rem;
        font-weight: bold;
    }

    #grand-total-display {
        font-size: 1.3rem;
        font-weight: bold;
        color: var(--gold-light);
        margin-top: 1rem;
    }

    .submit-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 50%, var(--gold) 100%);
        color: #1a1a1a;
        padding: 16px;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 1rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(192, 162, 61, 0.3);
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(192, 162, 61, 0.4);
    }

    .submit-btn:active {
        transform: translateY(1px);
    }

    .submit-btn i {
        margin-right: 8px;
    }

    .error-message {
        color: var(--error);
        font-size: 0.85rem;
        margin-top: 5px;
        display: none;
    }

    .input-group.error input {
        border-color: var(--error);
        animation: shake 0.5s;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-5px); }
        40%, 80% { transform: translateX(5px); }
    }
</style>
</head>
<body>
    <a href="analysis_report.php" class="back-btn">
    <i class="fas fa-arrow-left"></i> Back to Report
    </a>

    <div class="container">
        <h1 class="glow-title">
            <i class="fas fa-coins"></i>
            Financial Operations
        </h1>
        
        <form method="post">
        
            <div class="month-selector">
                <label>Select Month</label>
                <input type="month" name="month" 
                       max="<?= $current_month ?>" 
                       value="<?= $selected_month ?>"
                       required>
            </div>

           <div class="input-grid">
                <?php 
                $fields = [
                    'electricity' => 'Electricity (RM)',
                    'water' => 'Water (RM)',
                    'salaries' => 'Salaries (RM)',
                    'rent' => 'Rent (RM)',
                    'cost' => 'Cost (RM)',
                    'tax' => 'Tax (%)',
                    'other' => 'Other (RM)'
                ];


                foreach ($fields as $field => $label): 
                    $isPercentage = ($field === 'tax');
                ?>
                <div class="input-group <?= $isPercentage ? 'percentage-input' : 'currency-input' ?>">
                    <label><?= $label ?></label>
                    <input type="number" 
       name="<?= $field ?>" 
       step="<?= $isPercentage ? '0.1' : '0.01' ?>"
       min="0"
       <?= $isPercentage ? 'max="100"' : '' ?>
       value="<?= ($field === 'tax') ? ($current_data['tax_percentage'] ?? '0') : ($current_data[$field] ?? '0.00') ?>"
       required
       oninput="validateInput(this)">
                    <div class="error-message" id="error-<?= $field ?>">
                        <?= $isPercentage ? 'Percentage must be between 0-100%' : 'Value cannot be negative' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

           <div class="total-section">
                <span id="total-display">Total Expenses (without tax): RM 0.00</span><br>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i>
                Update Financial Records
            </button>
<input type="hidden" id="php-tax-amount" value="<?= number_format($tax_amount, 2, '.', '') ?>">
        </form>
    </div>

    <script>

document.querySelector('input[name="month"]').addEventListener('change', function() {
    const selectedMonth = this.value;
    if (selectedMonth) {
        window.location.href = `?month=${selectedMonth}`;
    }
});
// Input validation function
        function validateInput(input) {
            const field = input.name;
            const errorElement = document.getElementById(`error-${field}`);
            const isPercentage = (field === 'tax');
            
            let value = parseFloat(input.value) || 0;
            
           
 // Validate the input range
            if (value < 0) {
                input.value = 0;
                value = 0;
                errorElement.style.display = 'block';
            } else if (isPercentage && value > 100) {
                input.value = 100;
                value = 100;
                errorElement.style.display = 'block';
            } else {
                errorElement.style.display = 'none';
            }
            
            // Total update
            updateTotals();
        }

// Calculated total
function updateTotals() {
    let expensesTotal = 0;
    let grandTotal = 0;

    document.querySelectorAll('input[type="number"]:not([name="month"]):not([name="tax"])').forEach(input => {
        expensesTotal += parseFloat(input.value) || 0;
    });

    const taxAmount = parseFloat(document.getElementById('php-tax-amount').value) || 0;
    grandTotal = expensesTotal + taxAmount;

    document.getElementById('total-display').textContent = `Total Expenses: RM ${expensesTotal.toFixed(2)}`;
    document.getElementById('tax-amount-display').textContent = `Tax Amount: RM ${taxAmount.toFixed(2)}`;
    document.getElementById('grand-total-display').textContent = `Grand Total: RM ${grandTotal.toFixed(2)}`;
}

document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', updateTotals);
});

        // Initialization calculations
        updateTotals();
        
        // Form submission validation
        document.getElementById('expense-form').addEventListener('submit', function(e) {
            let valid = true;
            
          // Check all numeric inputs for non-negative numbers
            document.querySelectorAll('input[type="number"]:not([name="month"])').forEach(input => {
                const value = parseFloat(input.value) || 0;
                const isPercentage = (input.name === 'tax');
                
                if (value < 0) {
                    document.getElementById(`error-${input.name}`).style.display = 'block';
                    valid = false;
                } else if (isPercentage && value > 100) {
                    document.getElementById(`error-${input.name}`).style.display = 'block';
                    valid = false;
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please correct the highlighted errors before submitting.');
            }
        });

        document.querySelectorAll('.currency-input input').forEach(input => {
    input.addEventListener('input', function() {
        const value = this.value.replace(/[^0-9.]/g, '');
        this.value = value ? `RM ${value}` : 'RM ';
    });
    
    if (!input.value) input.value = 'RM ';
});

window.addEventListener('DOMContentLoaded', updateTotals);
    </script>
</body>
</html>
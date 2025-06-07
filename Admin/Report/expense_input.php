<?php
// expense_input.php
require 'db_conn.php';
session_start();

$current_month = date('Y-m');
$selected_month = $_GET['month'] ?? $current_month;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'month_year'  => $_POST['month'],
        'electricity' => $_POST['electricity'],
        'water'       => $_POST['water'],
        'salaries'    => $_POST['salaries'],
        'rent'        => $_POST['rent'],
        'cost'        => $_POST['cost'],
        'tax'         => $_POST['tax'],
        'other'       => $_POST['other'],
    ];


    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM monthly_expenses WHERE month_year = ?");
    $stmt->execute([$data['month_year']]);

    if ($stmt->fetch()) {
        // Update
        $sql = "UPDATE monthly_expenses SET 
                    electricity = ?, water = ?, salaries = ?, 
                    rent = ?, cost = ?, tax = ?, other = ? 
                WHERE month_year = ?";
        $pdo->prepare($sql)->execute([
            $data['electricity'], $data['water'], $data['salaries'],
            $data['rent'], $data['cost'], $data['tax'], $data['other'],
            $data['month_year']
        ]);
    } else {
        // Insert
        $sql = "INSERT INTO monthly_expenses 
                (month_year, electricity, water, salaries, rent, cost, tax, other) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute(array_values($data));
    }

    header("Location: analysis_report.php?month={$data['month_year']}");
    exit;
}

// Get existing data
$stmt = $pdo->prepare("SELECT * FROM monthly_expenses WHERE month_year = ?");
$stmt->execute([$selected_month]);
$current_data = $stmt->fetch();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Monthly Expenses Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --gold: #c0a23d;
            --dark-bg: #1a1a1a;
            --input-bg: #2d2d2d;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--dark-bg);
            color: #e8d48b;
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
      background: linear-gradient(to right, #c0a23d, #e8d48b);
      color: #000;
      font-weight: bold;
      padding: 10px 20px;
      border-radius: 10px;
      text-decoration: none;
      transition: 0.2s ease;
      margin-bottom: 2rem;
    }

    .back-btn:hover {
      background: #e8d48b;
      box-shadow: 0 0 10px #e8d48b;
    }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: linear-gradient(145deg, #1e1e1e, #2a2a2a);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            border: 1px solid rgba(192, 162, 61, 0.2);
        }

        h1.glow-title {
            text-align: center;
            margin: 0 0 2rem;
            background: linear-gradient(90deg, #f6d365, #c0a23d, #b38f2d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            font-family: 'Playfair Display', serif;
            letter-spacing: 0.05em;
        }

        .input-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 3rem;
    margin: 2rem 0;
    padding: 0 1rem;
}

.input-group {
    position: relative;
    min-width: 0;
    margin-bottom: 0rem;
}

.input-group label {
    display: block;
    margin-bottom: 0rem;
    color: #e8d48b;
    font-weight: 500;
    font-size: 1rem;
    letter-spacing: 0.03em;
    font-family: 'Segoe UI', system-ui, sans-serif;
}

.input-group input {
    width: 100%;
    padding: 12px 15px;
    background: var(--input-bg);
    border: 2px solid rgba(192, 162, 61, 0.3);
    border-radius: 8px;
    color: #fff;
    font-size: 1.05rem;
    font-family: 'Roboto', sans-serif;
    transition: all 0.3s ease;
}

.input-group.currency-input input {
    padding-left: 50px;
    text-align: right;
    font-family: 'Courier New', monospace;
    font-weight: 500;
}

.input-group.currency-input::before {
    content: 'RM';
    position: absolute;
    left: 15px;
    bottom: 12px;
    color: rgba(192, 162, 61, 0.8);
    font-weight: 700;
    font-size: 1.1rem;
    z-index: 2;
}

.input-group.month-selector::before {
    content: none;
}

.input-group input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 15px rgba(192, 162, 61, 0.25);
}

        .month-selector {
            padding: 0 1rem;
            margin-bottom: 2rem;
        }

        .month-selector input {
            width: 100%;
            padding: 12px;
            background: var(--input-bg);
            border: 2px solid rgba(192, 162, 61, 0.3);
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
            font-size: 1.05rem;
            transition: all 0.3s ease;
        }

        .currency-input {
            width: 100%;
            padding: 12px 12px 12px 45px;
            background: var(--input-bg);
            border: 2px solid rgba(192, 162, 61, 0.3);
            border-radius: 8px;
            color: #fff;
            font-family: 'Consolas', monospace;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .currency-input::before {
            content: 'RM';
            position: absolute;
            left: 15px;
            bottom: 12px;
            color: rgba(192, 162, 61, 0.8);
            font-weight: 700;
            font-size: 1.1rem;
        }

        input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 12px rgba(192, 162, 61, 0.2);
        }

        .total-section {
            text-align: left;
            margin: 2.5rem 1rem;
            font-size: 1.3rem;
            padding: 1.2rem;
            background: rgba(192, 162, 61, 0.1);
            border-radius: 8px;
            font-family: 'Consolas', monospace;
        }

        button.submit-btn {
    width: calc(100% - 2rem);
    margin: 0 1rem;
    background: linear-gradient(
        135deg, 
        #c0a23d 0%, 
        #e8d48b 50%, 
        #c0a23d 100%
    );
    color: #1a1a1a;
    padding: 16px;
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    transform: translateY(0);
    box-shadow: 
        0 2px 8px rgba(192, 162, 61, 0.2),
        0 4px 20px rgba(192, 162, 61, 0.1);
}

button.submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 200%;
    height: 100%;
    background: linear-gradient(
        90deg,
        rgba(255,255,255,0) 0%,
        rgba(255,255,255,0.15) 50%,
        rgba(255,255,255,0) 100%
    );
    transition: all 0.6s ease;
}

button.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 
        0 4px 15px rgba(192, 162, 61, 0.3),
        0 6px 25px rgba(192, 162, 61, 0.2);
}

button.submit-btn:hover::before {
    left: 100%;
}

button.submit-btn:active {
    transform: translateY(1px);
    box-shadow: 
        0 1px 5px rgba(192, 162, 61, 0.3),
        inset 0 2px 4px rgba(0,0,0,0.1);
}

button.submit-btn i {
    position: relative;
    z-index: 2;
    transition: transform 0.3s ease;
}

button.submit-btn:hover i {
    transform: scale(1.1) rotate(-5deg);
}

button.submit-btn.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 24px;
    height: 24px;
    border: 3px solid rgba(26,26,26,0.3);
    border-top-color: #1a1a1a;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
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
       
            <div class="input-grid ">
                <?php foreach (['electricity', 'water', 'salaries', 'rent', 'cost', 'tax', 'other'] as $field): ?>
                <div class="input-group">
                    <label><?= ucfirst($field) ?></label>
                    <input type="number" 
                           class="currency-input"
                           name="<?= $field ?>" 
                           step="0"
                           value="<?= $current_data[$field] ?? 0 ?>"
                           required>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="total-section">
                <span id="total-display">Total: RM 0.00</span>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i>
                Update Financial Records
            </button>
        </form>
    </div>

    <script>

document.querySelector('input[name="month"]').addEventListener('change', function() {
    const selectedMonth = this.value;
    if (selectedMonth) {
        window.location.href = `?month=${selectedMonth}`;
    }
});

        // Real-time totals
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', updateTotal);
        });

        function updateTotal() {
            let total = 0;
            document.querySelectorAll('input[type="number"]:not([name="month"])').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('total-display').textContent = `Total: RM ${total.toFixed(2)}`;
        }
        
    // Initialization calculations
        updateTotal();
    </script>
</body>
</html>
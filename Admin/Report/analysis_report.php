<?php
// analysis_report.php
require 'db_conn.php';
session_start();

// Get current month-year
$current_month = date('Y-m');
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profit Analysis Report</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --gold: #c0a23d;
            --dark-bg: #1a1a1a;
        }
        body {
            background: var(--dark-bg);
            color: white;
            font-family: Arial;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .expense-btn {
            background: var(--gold);
            color: black;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        /* Add previous back-btn styles here */
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="../Main Page/main_page.php" class="back-btn">
                <i class="fas fa-house"></i> Back To Main Page
            </a>
            <button onclick="window.location.href='expense_input.php'" class="expense-btn">
                Input Expenses
            </button>
        </div>

        <!-- Month Selector -->
        <form method="GET">
            <input type="month" name="month" max="<?= $current_month ?>" 
                   value="<?= $selected_month ?>">
            <button type="submit">Load</button>
        </form>

        <?php
        // Calculate income from completed orders
        $income = 0;
        $stmt = $pdo->prepare("SELECT SUM(total) FROM orders 
                              WHERE status = 'completed' 
                              AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$selected_month]);
        $income = $stmt->fetchColumn();

        // Calculate refunds from refund requests
        $refunds = 0;
        $stmt = $pdo->prepare("SELECT SUM(o.total) FROM refund_requests rr
                              JOIN orders o ON rr.order_id = o.order_id
                              WHERE rr.status = 'completed'
                              AND DATE_FORMAT(rr.date, '%Y-%m') = ?");
        $stmt->execute([$selected_month]);
        $refunds = $stmt->fetchColumn();

        // Get monthly expenses
        $stmt = $pdo->prepare("SELECT * FROM monthly_expenses 
                              WHERE month_year = ?");
        $stmt->execute([$selected_month]);
        $expenses = $stmt->fetch();

        if($expenses) {
            $total_expenses = array_sum([
                $expenses['electricity'],
                $expenses['water'],
                $expenses['salaries'],
                $expenses['rent'],
                $expenses['cost'],
                $expenses['tax'],
                $expenses['other']
            ]);
            
            $net_profit = ($income + $refunds) - $total_expenses;
        }
        ?>
        
        <!-- Charts Container -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <canvas id="expenseChart"></canvas>
            </div>
            <div>
                <canvas id="profitChart"></canvas>
            </div>
        </div>

        <!-- Detailed Report Table -->
        <h3>Detailed Financial Report for <?= $selected_month ?></h3>
        <table border="1">
            <tr><th>Income</th><td><?= number_format($income, 2) ?></td></tr>
            <tr><th>Refunds</th><td><?= number_format($refunds, 2) ?></td></tr>
            <?php if($expenses): ?>
                <tr><th>Electricity</th><td><?= $expenses['electricity'] ?></td></tr>
                <!-- Add all expense rows -->
                <tr><th>Total Expenses</th><td><?= $total_expenses ?></td></tr>
                <tr><th>Net Profit</th><td><?= $net_profit ?></td></tr>
            <?php endif; ?>
        </table>

        <script>
            // Expense Distribution Pie Chart
            new Chart(document.getElementById('expenseChart'), {
                type: 'pie',
                data: {
                    labels: ['Electricity', 'Water', 'Salaries', 'Rent', 'Cost', 'Tax', 'Other'],
                    datasets: [{
                        data: <?= json_encode(array_values($expenses ? array_slice($expenses, 2) : [])) ?>,
                        backgroundColor: ['#c0a23d','#e8d48b','#a38d3d','#7a6a2f','#524822','#2a2415','#000']
                    }]
                }
            });

            // Profit Analysis Bar Chart
            new Chart(document.getElementById('profitChart'), {
                type: 'bar',
                data: {
                    labels: ['Income', 'Refunds', 'Expenses', 'Net Profit'],
                    datasets: [{
                        label: 'Amount',
                        data: [<?= $income ?>, <?= $refunds ?>, <?= $total_expenses ?? 0 ?>, <?= $net_profit ?? 0 ?>],
                        backgroundColor: '#c0a23d'
                    }]
                }
            });
        </script>
    </div>
</body>
</html>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --gold: #c0a23d;
            --dark-bg: #1a1a1a;
        }
        body {
            background: var(--dark-bg);
            color: white;
            font-family: Arial;
            text-align: center;
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
  z-index: -1; /* ⬅ 放底层 */
}

/* 星尘粒子 */
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
  z-index: -2; /* ⬅ 更底层 */
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

        .date-selector {
    display: flex;
    gap: 12px;
    max-width: 400px;
    margin: 1.5rem auto;
    background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
    padding: 0.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    border: 1px solid #c0a23d55;
}

.date-selector input[type="month"] {
    flex: 1;
    padding: 12px 16px;
    background: #2a2a2a;
    border: 2px solid #c0a23d;
    border-radius: 8px;
    color: #e8d48b;
    font-size: 16px;
    transition: all 0.3s ease;
}

.date-selector input[type="month"]:focus {
    outline: none;
    border-color: #e8d48b;
    box-shadow: 0 0 8px #c0a23d66;
}

.date-selector button {
    background: linear-gradient(135deg, #c0a23d, #e8d48b);
    color: #1a1a1a;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.date-selector button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(192, 162, 61, 0.3);
}

/* 自定义下拉箭头 */
.date-selector input[type="month"]::-webkit-calendar-picker-indicator {
    filter: invert(0.8);
    padding: 4px;
    cursor: pointer;
    border-radius: 4px;
    transition: background 0.3s;
}

.date-selector input[type="month"]::-webkit-calendar-picker-indicator:hover {
    background: #c0a23d33;
}

        .charts-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 50px;
            margin-bottom: 30px;
        }
        canvas {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
        }
        table {
            margin: 0 auto;
            border-collapse: collapse;
            width: 80%;
            background: #fff;
            color: #000;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd;
        }
        th {
            background-color: var(--gold);
            color: #000;
        }

        @media print {
    body {
        zoom: 90%; 
        margin: 0;
        padding: 0;
    }

    .back-btn,
    .date-selector,
    .header a,
    .header button {
        display: none !important; /* 打印时隐藏导航和按钮 */
    }

    canvas {
        max-width: 100% !important;
        height: auto !important;
    }

    table {
        page-break-inside: avoid;
        font-size: 14px;
    }
}
    </style>
</head>
<body>
    <div class="container">
    <div class="header">
    <a href="../Main Page/main_page.php" class="back-btn">
        <i class="fas fa-house"></i> Back To Main Page
    </a>
    <div style="display: flex; gap: 15px;">
        <button onclick="window.location.href='expense_input.php'" class="back-btn">
            Input Expenses
        </button>
        <button onclick="window.print()" class="back-btn">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>
</div>


        <!-- Month Selector -->
        <form method="GET" class="date-selector">
    <input type="month" 
           name="month" 
           max="<?= $current_month ?>" 
           value="<?= $selected_month ?>"
           title="Select report month"
           aria-label="Select month">
    <button type="submit" 
            aria-label="Load data for selected month">
        <i class="fas fa-search"></i> Show Report
    </button>
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
        $stmt = $pdo->prepare("SELECT SUM(r.total) FROM refund_requests r
                      WHERE r.status = 'approved'
                      AND DATE_FORMAT(r.created_at, '%Y-%m') = ?");
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
        <div class="charts-container">
            <div>
                <canvas id="expenseChart" width="400" height="400"></canvas>
            </div>
            <div>
                <canvas id="profitChart" width="600" height="400"></canvas>
            </div>
        </div>

        <!-- Detailed Report Table -->
        <h3>Detailed Financial Report for <?= $selected_month ?></h3>
<table>
    <tr><th>Income</th><td>RM <?= number_format($income, 2) ?></td></tr>
    <tr><th>Refunds</th><td>RM <?= number_format($refunds, 2) ?></td></tr>
    <?php if($expenses): ?>
    <tr><th>Electricity</th><td>RM <?= number_format($expenses['electricity'], 2) ?></td></tr>
    <tr><th>Water</th><td>RM <?= number_format($expenses['water'], 2) ?></td></tr>
    <tr><th>Salaries</th><td>RM <?= number_format($expenses['salaries'], 2) ?></td></tr>
    <tr><th>Rent</th><td>RM <?= number_format($expenses['rent'], 2) ?></td></tr>
    <tr><th>Cost</th><td>RM <?= number_format($expenses['cost'], 2) ?></td></tr>
    <tr><th>Tax</th><td>RM <?= number_format($expenses['tax'], 2) ?></td></tr>
    <tr><th>Other</th><td>RM <?= number_format($expenses['other'], 2) ?></td></tr>
    <tr><th>Total Expenses</th><td>RM <?= number_format($total_expenses, 2) ?></td></tr>
    <tr><th>Net Profit</th><td>RM <?= number_format($net_profit, 2) ?></td></tr>
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
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED']
                    }]
                },
                options: {
                    responsive: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#000',
                                font: {
                                    size: 14
                                }
                            }
                        }
                    }
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
                },
                options: {
                    responsive: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#000'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#000'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        </script>
    </div>
</body>
</html>

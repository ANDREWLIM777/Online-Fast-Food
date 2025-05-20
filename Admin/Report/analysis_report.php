<?php
// analysis_report.php
require 'db_conn.php';
session_start();

// Get current month-year
$current_month = date('Y-m');
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;

$reportMonth = DateTime::createFromFormat('Y-m', $selected_month)->format('F Y');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profit Analysis Report</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Roboto:wght@300;500&display=swap" rel="stylesheet">
    <style>

 .header {
    left: 0;
    right: 0;   
    position: fixed;
    top: 0;
    width: 100%;
    background: 
        linear-gradient(135deg, #000000 0%, #0c0a10 100%),
        repeating-linear-gradient(-30deg, 
            transparent 0px 10px, 
            #f4e3b215 10px 12px,
            transparent 12px 22px);
    padding: 1.8rem 0;
    box-shadow: 0 4px 25px rgba(0,0,0,0.06);
    z-index: 999;
    display: flex;
    justify-content: center;
    border-bottom: 1px solid #eee3c975;
    overflow: hidden;
}

.title-group {
    position: relative;
    left:36rem;
    text-align: center;
    padding: 0 2.5rem;
}

.main-title {
    font-size: 2.1rem;/* 中间尺寸 */
    background: linear-gradient(45deg, #c0a23d, #907722);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-family: 'Playfair Display', serif;
    letter-spacing: 1.8px;
    line-height: 1.15;
    text-shadow: 1px 1px 2px rgba(255,255,255,0.3);
    margin-bottom: 0.4rem;
    transition: all 0.3s ease;
}

.sub-title {
    font-size: 1.05rem;
    color: #907722;
    font-family: 'Roboto', sans-serif;
    font-weight: 400;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    opacity: 0.9;
    position: relative;
    display: inline-block;
    padding: 0 15px;
}

/* 双装饰线动画 */
.sub-title::before,
.sub-title::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 35px;
    height: 1.2px;
    background: linear-gradient(90deg, #c9a227aa, transparent);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.sub-title::before {
    left: -30px;
    transform: translateY(-50%) rotate(-15deg);
}

.sub-title::after {
    right: -30px;
    transform: translateY(-50%) rotate(15deg);
}

.title-group:hover .sub-title::before {
    left: -35px;
    width: 35px;
}

.title-group:hover .sub-title::after {
    right: -35px;
    width: 35px;
}

/* 动态光晕背景 */
.header::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 50% 50%, 
        #f4e3b210 0%, 
        transparent 60%);
    animation: auraPulse 8s infinite;
    pointer-events: none;
}

@keyframes auraPulse {
    0% { transform: scale(0.8); opacity: 0.3; }
    50% { transform: scale(1.2); opacity: 0.1; }
    100% { transform: scale(0.8); opacity: 0.3; }
}

/* 微光粒子 */
.header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 30%, #f4e4b239 1px, transparent 2px),
        radial-gradient(circle at 80% 70%, #f4e4b236 1px, transparent 2px);
    background-size: 40px 40px;
    animation: stardust 20s linear infinite;
}

@keyframes stardust {
    0% { background-position: 0 0, 100px 100px; }
    100% { background-position: 100px 100px, 0 0; }
}

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
    .header button,
    .title-group {
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
<body data-month="<?= $selected_month ?>">

  <div class="header">
    <!-- 左侧按钮 -->
    <div style="position: absolute; left: 2rem; top: 50%; transform: translateY(-50%);">
        <a href="../Main Page/main_page.php" class="back-btn">
            <i class="fas fa-house"></i> Back To Main Page
        </a>
    </div>

    <!-- 中间标题 -->
    <div class="title-group">
        <div class="main-title">BRIZO MELAKA</div>
        <div class="sub-title">Profit Analysis Page</div>
    </div>

    <!-- 右侧按钮 -->
    <div style="position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); display: flex; gap: 10px;">
        <button onclick="window.location.href='expense_input.php'" class="back-btn">Input Expenses</button>
        <button onclick="generatePDF()" class="back-btn"><i class="fas fa-file-pdf"></i> Export PDF</button>
        <button onclick="window.print()" class="back-btn"><i class="fas fa-print"></i> Print Report</button>
    </div>
</div>

<div class="container" style="padding-top: 110px;">
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
            
            $net_profit = ($income) - $total_expenses;
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
                    labels: ['Income', 'Expenses', 'Net Profit'],
                    datasets: [{
                        label: 'Amount',
                        data: [<?= $income ?>, <?= $total_expenses ?? 0 ?>, <?= $net_profit ?? 0 ?>],
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

function generatePDF() {
        const sourceElement = document.querySelector('.container');
        const reportMonth = '<?= $reportMonth ?>'; // 使用格式化后的月份
        const currentDate = new Date();
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        const formattedDate = currentDate.toLocaleDateString('en-US', options);

        // 克隆容器
        const clone = sourceElement.cloneNode(true);
        clone.style.paddingTop = '0';

        // 添加标题
        const reportTitle = document.createElement('h2');
        reportTitle.textContent = `Profit Analysis Report for ${reportMonth} - Generated on ${formattedDate}`;
        reportTitle.style.textAlign = 'center';
        reportTitle.style.marginBottom = '1rem';
        clone.insertBefore(reportTitle, clone.firstChild);

        // 替换图表 canvas 为 img
        const canvasIds = ['expenseChart', 'profitChart'];
        canvasIds.forEach(id => {
            const canvas = document.getElementById(id);
            const cloneCanvas = clone.querySelector(`#${id}`);

            if (canvas && cloneCanvas) {
                const img = document.createElement('img');
                img.src = canvas.toDataURL('image/png');
                img.style.maxWidth = '350px';
                img.style.display = 'block';
                img.style.margin = '0 auto 0rem';
                img.style.pageBreakInside = 'avoid';
                cloneCanvas.parentNode.replaceChild(img, cloneCanvas);
            }
        });

        // 移除不需要导出的元素
        clone.querySelectorAll('.date-selector').forEach(el => el.remove());

        // 放入隐藏容器
        const tempContainer = document.createElement('div');
        tempContainer.style.position = 'absolute';
        tempContainer.style.left = '-9999px';
        tempContainer.appendChild(clone);
        document.body.appendChild(tempContainer);

        const opt = {
            margin: 0.3,
            filename: `Profit_Analysis_${reportMonth}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().from(clone).set(opt).save().then(() => {
            document.body.removeChild(tempContainer);
        });
    }

</script>

    </div>
</body>
</html>

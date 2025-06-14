<?php
require '../Admin_Account/db.php';

// Determine selected month
$maxMonth = date('Y-m');
$selected = $_GET['month'] ?? $maxMonth;

// Fetch monthly averages and counts for selected month
$stmt = $conn->prepare("
  SELECT 
    ROUND(AVG(rating), 2) AS avg_rating,
    COUNT(*) AS cnt
  FROM feedback
  WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
");
$stmt->bind_param('s', $selected);
$stmt->execute();
$one = $stmt->get_result()->fetch_assoc();

// Fetch feedback details for selected month
$stmt = $conn->prepare("
  SELECT 
    f.order_id,
    c.fullname AS customer_name,
    f.rating, f.comments, f.evidence_path, f.created_at
  FROM feedback f
  JOIN customers c ON c.id = f.customer_id
  WHERE DATE_FORMAT(f.created_at, '%Y-%m') = ?
  ORDER BY f.created_at DESC
");
$stmt->bind_param('s', $selected);
$stmt->execute();
$feedbacks = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Feedback Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { background: #121212; color: #e8c97a; font-family: 'Segoe UI', sans-serif; padding: 20px; }
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

    .container { max-width: 1200px; margin: 0 auto; }
    .back-btn, .picker-btn { background: #1e1e1e; color: #e8c97a; border: 1px solid #c0a23d;
                padding: 10px 18px; border-radius: 8px; display: inline-block; text-decoration: none; }
    .back-btn:hover, .picker-btn:hover { background: #c0a23d; color: #000; }
    h1 { text-align: center; margin: 20px 0; font-size: 2.2rem;
         background: linear-gradient(90deg, #c0a23d, #907722);
         -webkit-background-clip: text; -webkit-text-fill-color: transparent;}
    .selector { text-align: center; margin-bottom: 20px; }
    .summary { background: #1e1e1e; padding: 16px; border-radius: 8px; text-align: center; margin-bottom: 30px;
               box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
    .summary .month { font-size: 1.2rem; color: #e8d48b; }
    .summary .avg { font-size: 1.6rem; color: #c0a23d; margin-top: 8px; }
    table { width: 100%; border-collapse: collapse; background: #1e1e1e;
            border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
    th,td { padding: 14px 12px; border-left: 1px solid #2a2a2a; vertical-align: top; }
    th { background: #2a2a2a; color: #e8d48b; font-size: 1rem; }
    tr:hover { background: #2c2c2c; }
    .rating { color: #c0a23d; font-weight: bold; }
    .comments { font-style: italic; color: #ccc; }
    .evidence img { max-width: 120px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
    .footer { text-align: center; margin-top: 40px; font-size: 0.9rem; color: #888; }
    .month-picker-form {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 14px;
  margin: 20px auto 30px;
  background: #1a1a1a;
  padding: 16px 24px;
  border-radius: 10px;
  border: 1px solid #c0a23d40;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
  max-width: 450px;
}

.month-picker-form input[type="month"] {
  padding: 10px 16px;
  border: 2px solid #c0a23d88;
  border-radius: 8px;
  background: #2a2a2a;
  color: #e8d48b;
  font-size: 1rem;
  font-weight: 500;
  font-family: inherit;
  outline: none;
  transition: border-color 0.3s ease;
}

.month-picker-form input[type="month"]:focus {
  border-color: #e8c97a;
  box-shadow: 0 0 5px #e8c97a55;
}

.month-picker-form button {
  background: linear-gradient(135deg, #c0a23d, #e8d48b);
  color: #1a1a1a;
  font-weight: bold;
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.95rem;
  transition: background 0.3s ease;
}

.month-picker-form button:hover {
  background: #e8d48b;
  color: #000;
}

  </style>
</head>
<body>
  <div class="container">
    <a href="more.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to More Page</a>
    <h1> <i class="fas fa-file-alt"></i> Customer Feedback Records</h1>

<div class="selector">
  <form method="GET" class="month-picker-form">
    <input type="month" name="month"
           value="<?= htmlspecialchars($selected) ?>"
           max="<?= htmlspecialchars($maxMonth) ?>"
           required>
    <button type="submit"><i class="fas fa-search"></i> View</button>
  </form>
</div>


    <div class="summary">
      <div class="month"><?= htmlspecialchars(date("F Y", strtotime($selected . '-01'))) ?></div>
      <div class="avg">
        <?= $one['cnt'] ? "{$one['avg_rating']} / 5 ({$one['cnt']} feedback)" : 'No feedback this month' ?>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Rating</th>
          <th>Comments</th>
          <th>Evidence</th>
          <th>Submitted At</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($feedbacks->num_rows): ?>
          <?php while($r = $feedbacks->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($r['order_id']) ?></td>
              <td><?= htmlspecialchars($r['customer_name']) ?></td>
              <td class="rating"><?= htmlspecialchars($r['rating']) ?> / 5</td>
              <td class="comments"><?= nl2br(htmlspecialchars($r['comments'])) ?></td>
              <td class="evidence">
                <?= $r['evidence_path'] ? "<img src=\"".htmlspecialchars($r['evidence_path'])."\" alt=\"evidence\">" : '—' ?>
              </td>
              <td><?= htmlspecialchars($r['created_at']) ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align:center; padding:20px;">No feedback this month.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="footer">Admin Panel — Feedback Overview</div>
  </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Index</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
    left:0rem;
    text-align: center;
    padding: 0 2.5rem;
}

.main-title {
    font-size: 2.1rem;
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

        body {
            background: #1a1a1a;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            color: white;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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

        .title {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            background: linear-gradient(45deg, #c0a23d, #907722);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 60px;
            text-align: center;
        }

        .button-group {
            display: flex;
            gap: 100px;
        }

        .report-btn {
            background: linear-gradient(145deg, #c0a23d, #e8d48b);
            border: none;
            color: #000;
            font-size: 1.8rem;
            font-weight: bold;
            padding: 30px 60px;
            border-radius: 20px;
            cursor: pointer;
            box-shadow: 0 6px 15px rgba(192, 162, 61, 0.4);
            transition: transform 0.2s, box-shadow 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .report-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(192, 162, 61, 0.6);
        }

        .report-btn i {
            font-size: 2rem;
        }

        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
                gap: 30px;
            }
        }
    </style>
</head>
<body>

  <div class="header">

    <div style="position: absolute; left: 2rem; top: 50%; transform: translateY(-50%);">
        <a href="../Main Page/main_page.php" class="back-btn">
            <i class="fas fa-house"></i> Back To Main Page
        </a>
    </div>


    <div class="title-group">
        <div class="main-title">BRIZO MELAKA</div>
        <div class="sub-title">Final Report Generation Page</div>
    </div>

</div>

    <div class="title">Select Report Type</div>

    <div class="button-group">
        <a href="food_analysis.php" class="report-btn">
            <i class="fas fa-chart-pie"></i> Food Analysis Report
        </a>

        <a href="analysis_report.php" class="report-btn">
            <i class="fas fa-chart-line"></i> Profit Analysis Report
        </a>
    </div>

</body>
</html>

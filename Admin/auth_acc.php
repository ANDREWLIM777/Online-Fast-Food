<?php


session_start();

// Determine if you are logged in
if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_role'])) {
    header("Location: ../Admin_Account/login.php");
    exit();
}

function check_permission($role_required) {
    if ($_SESSION['user_role'] !== $role_required) {
        echo '
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Access Denied</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    font-family: Arial, sans-serif;
                }
                .modal-box {
                    background: #181818;
                    width: 60%;
                    height: 60%;
                    color: white;
                    padding: 2rem;
                    border-radius: 14px;
                    box-shadow: 0 0 30px rgba(255, 215, 0, 0.2);
                    border: 2px solid #c0a23d;
                    position: relative;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    text-align: center;
                }
                .modal-box h2 {
                    color: #e8d48b;
                    font-size: 2.5rem;
                    margin-bottom: 1.5rem;
                }
                .modal-box p {
                    font-size: 1.3rem;
                    color: #ccc;
                    max-width: 80%;
                }
                .close-btn {
                    position: absolute;
                    top: 1rem;
                    left: 1rem;
                    background: linear-gradient(to right, #c0a23d, #e8d48b);
                    color: #000;
                    font-size: 1.1rem;
                    border: none;
                    border-radius: 12px;
                    padding: 0.6rem 1.2rem;
                    font-weight: bold;
                    cursor: pointer;
                    box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
                    transition: all 0.2s ease-in-out;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .close-btn:hover {
                    background: #e8d48b;
                    transform: scale(1.05);
                }
            </style>
        </head>
        <body>
            <div class="modal-box">
                <button class="close-btn" onclick="window.location.href=\'../Manage_Account/index.php\'">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <h2>Access Denied</h2>
                <p>Only ' . htmlspecialchars($role_required) . ' allowed.</p>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = "../Manage_Account/index.php";
                }, 2500);
            </script>
        </body>
        </html>';
        exit();
    }
}

?>



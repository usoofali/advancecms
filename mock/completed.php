<?php
session_start();
require 'db.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Process the submission and calculate score
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="asset/logo.jpg">
    <title>Exam Completed</title>
    <!-- Modern Typography: Outfit from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-hover: #45a049;
            --bg-overlay: rgba(0, 0, 0, 0.4);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-main: #2d3436;
            --text-muted: #636e72;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-image: linear-gradient(var(--bg-overlay), var(--bg-overlay)), url('asset/background.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-card {
            width: 100%;
            max-width: 500px;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: slideUp 0.7s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
        }

        .icon-box svg {
            width: 40px;
            height: 40px;
        }

        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        h3 {
            font-size: 1rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 30px;
            font-weight: 400;
        }

        .btn-home {
            display: inline-block;
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white !important;
            border: none;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.25);
        }

        .btn-home:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.35);
        }

        .btn-home:active {
            transform: translateY(0);
        }

        @media (max-width: 480px) {
            .success-card {
                padding: 40px 25px;
                border-radius: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="success-card">
        <div class="icon-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12" />
            </svg>
        </div>
        <h1>Exam Submitted!</h1>
        <h3>Your examination has been successfully recorded. Kindly walk out from the exam hall quietly.</h3>
        <a href="index.php" class="btn-home">Return to Home</a>
    </div>
</body>

</html>
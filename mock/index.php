<?php
session_start();
require 'db.php'; // Include your database connection

$exams = $pdo->prepare('SELECT * FROM exams WHERE status = 0');
$exams->execute();
$exams = $exams->fetchall();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = strtoupper($_POST['password']);
    $exam = $_POST['exam'];

    // Check if user exists
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
    $stmt->execute([$exam]);
    $user_exam = $stmt->fetch();

    if ($user && $password == $user['password']) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'] ?? 'student';

        if ($_SESSION['role'] === 'admin') {
            // header("Location: admin_dashboard.php");
            // exit();
        }

        if ($exam != '') {
            if ($user['level'] == $user_exam['level'] && $user['dept'] == $user_exam['dept']) {
                $_SESSION['exam_id'] = $exam;
                $_SESSION['dept_id'] = $user['dept'];
                header('Location: instruction.php');
                exit();
            } else {
                $error = 'Exam selection is invalid for your level/department.';
            }
        } else {
            $error = 'Please select an exam.';
        }
    } else {
        $error = 'Incorrect admission number or password.';
    }
}

if (isset($_GET['error'])) {
    $error = 'Your examination has been submitted or session has expired.';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="asset/logo.jpg">
    <title>Semester Examination - Login</title>
    <!-- Modern Typography: Outfit from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <script src="jquery/jquery-3.2.1.min.js"></script>
    <script src="swal/sweetalert2.all.min.js"></script>
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-hover: #45a049;
            --bg-overlay: rgba(0, 0, 0, 0.4);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-main: #2d3436;
            --text-muted: #636e72;
            --error-bg: #ffeaa7;
            --error-text: #d63031;
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

        .login-card {
            width: 100%;
            max-width: 900px;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 0;
            display: flex;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.6s ease-out;
        }

        .brand-section {
            background: rgba(255, 255, 255, 0.5);
            padding: 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            border-right: 1px solid rgba(0, 0, 0, 0.05);
        }

        .form-section {
            padding: 40px;
            flex: 1.2;
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            margin-bottom: 20px;
        }

        .logo-container img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }


        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            padding-left: 5px;
        }

        select,
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid transparent;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            color: var(--text-main);
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            font-family: inherit;
        }

        select:focus,
        input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.1);
        }

        button {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-top: 5px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        button:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        /* Customize select arrow */
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23636e72' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 18px;
            padding-right: 45px;
        }

        @media (max-width: 850px) {
            .login-card {
                flex-direction: column;
                max-width: 450px;
                border-radius: 24px;
                height: auto;
            }

            .brand-section {
                padding: 30px;
                border-right: none;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            }

            .form-section {
                padding: 30px;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                border-radius: 0;
                height: 100vh;
                max-width: 100%;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="brand-section">
            <div class="logo-container">
                <img src="asset/logo.jpg" alt="Logo">
            </div>
            <h1>Semester Examinations</h1>
            <p class="subtitle">Welcome back. Please verify your identity to begin the exam session.</p>
        </div>

        <div class="form-section">
            <form id="loginForm" method="POST">
                <div class="form-group">
                    <label>Select Examination</label>
                    <select name="exam" required>
                        <option value=''>Choose an exam...</option>
                        <?php
                        foreach ($exams as $exam) {
                            $depts = $pdo->prepare('SELECT * FROM dept WHERE id = ?');
                            $depts->execute([$exam['dept']]);
                            $dept = $depts->fetch();
                            ?>
                            <option value='<?php echo $exam['id']; ?>'>
                                <?php echo htmlspecialchars($exam['level'].' : '.$dept['code'].' - '.$exam['code'].' - '.$exam['title']); ?>
                            </option>
                            <?php
                        }
?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Admission Number</label>
                    <input type="text" name="username" placeholder="Admission Number" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit">Login</button>
            </form>

        </div>
    </div>
</body>

</html>
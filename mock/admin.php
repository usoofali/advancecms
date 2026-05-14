<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = strtoupper($_POST['password']);

    // Check if user exists and is an admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $password == $user['password']) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = 'admin';

        header('Location: admin_dashboard.php');
        exit();
    } else {
        $error = 'Invalid administrative credentials.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="asset/logo.jpg">
    <title>Admin Portal - Access Control</title>
    <!-- Modern Typography: Outfit from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #3f51b5;
            /* Indigo */
            --primary-hover: #303f9f;
            --bg-overlay: rgba(0, 0, 0, 0.6);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-main: #1a1a1a;
            --text-muted: #666;
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
            max-width: 450px;
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 45px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.7s ease-out;
            text-align: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            margin-bottom: 25px;
        }

        .logo-container img {
            width: 90px;
            height: 90px;
            object-fit: contain;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 35px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            padding-left: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            color: var(--text-main);
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 5px rgba(63, 81, 181, 0.15);
        }

        .error-alert {
            background: rgba(231, 76, 60, 0.1);
            color: #d63031;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            border: 1px solid rgba(231, 76, 60, 0.2);
            font-weight: 600;
        }

        button {
            width: 100%;
            padding: 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-top: 10px;
            box-shadow: 0 8px 18px rgba(63, 81, 181, 0.3);
        }

        button:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(63, 81, 181, 0.4);
        }

        .back-link {
            display: block;
            margin-top: 30px;
            font-size: 0.9rem;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="logo-container">
            <img src="asset/logo.jpg" alt="Logo">
        </div>
        <h1>Admin Portal</h1>
        <p class="subtitle">Secure administrative access control panel.</p>

        <?php if (isset($error)) { ?>
            <div class="error-alert">
                <?php echo $error; ?>
            </div>
        <?php } ?>

        <form method="POST">
            <div class="form-group">
                <label>Admin Username</label>
                <input type="text" name="username" placeholder="Username" required autocomplete="off">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit">Authentication Required</button>
        </form>

        <a href="index.php" class="back-link">Return to Student Portal</a>
    </div>
</body>

</html>
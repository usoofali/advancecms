<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Exam Management</title>
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
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-main: #2d3436;
            --text-muted: #636e72;
            --sidebar-width: 260px;
        }

        body {
            background-color: #f1f3f5;
            background-image: linear-gradient(rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.8)), url('asset/background.jpg');
            background-size: cover;
            background-attachment: fixed;
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
            min-height: 100vh;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border-right: 1px solid var(--glass-border);
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            padding: 0 10px;
        }

        .brand-box img {
            width: 40px;
            border-radius: 8px;
        }

        .brand-box h1 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            color: var(--primary-color);
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(76, 175, 80, 0.1);
            color: var(--primary-color);
        }

        .nav-link.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .logout-box {
            padding-top: 20px;
            border-top: 1px solid var(--glass-border);
        }

        /* Main Content */
        .admin-main {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            padding: 40px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }

        .page-title p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.95rem;
        }

        /* Generic Glass Card */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="brand-box">
                <img src="asset/logo.jpg" alt="Logo">
                <h1>Admin Panel</h1>
            </div>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="admin_dashboard.php"
                        class="nav-link <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_exams.php"
                        class="nav-link <?php echo $current_page == 'manage_exams.php' ? 'active' : ''; ?>">
                        <span>Manage Exams</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'manage_sessions.php' ? 'active' : ''; ?>"
                        href="manage_sessions.php">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" class="me-2">
                            <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
                        </svg>
                        Active Sessions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 mb-2 <?php echo basename($_SERVER['PHP_SELF']) == 'manage_results.php' ? 'active' : ''; ?>"
                        href="manage_results.php">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" class="me-2">
                            <path
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        View Results
                    </a>
                </li>
                <li class="nav-item">
                    <a href="upload.php" class="nav-link <?php echo $current_page == 'upload.php' ? 'active' : ''; ?>">
                        <span>Upload Questions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="manage_students.php"
                        class="nav-link <?php echo $current_page == 'manage_students.php' ? 'active' : ''; ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" class="me-2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87m-4-12a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Manage Students</span>
                    </a>
                </li>
            </ul>

            <div class="logout-box">
                <a href="logout.php" class="nav-link text-danger">
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <main class="admin-main">
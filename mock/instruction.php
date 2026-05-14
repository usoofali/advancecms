<?php
session_start();
require 'db.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$name = $_SESSION['fullname'];
$exam_id = $_SESSION['exam_id'];
$dept_id = $_SESSION['dept_id'];

$dept = $pdo->prepare('SELECT * FROM dept WHERE id = ?');
$dept->execute([$dept_id]);
$dept = $dept->fetch();

$exam = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$exam->execute([$exam_id]);
$exam = $exam->fetch();

// Check for existing unsubmitted session
$check_session = $pdo->prepare('SELECT * FROM exam_session WHERE user_id = ? AND exam = ? AND submit_status = 0 ORDER BY attempt_number DESC LIMIT 1');
$check_session->execute([$user_id, $exam_id]);
$existing_session = $check_session->fetch();

$is_continuable = $existing_session && (time() < strtotime($existing_session['stop_at']));
$button_text = $is_continuable ? 'Continue Examination' : 'Start Examination';

// Fetch the attempt info for the current or next attempt
$latest_session = $pdo->prepare('SELECT attempt_number, attempt_type FROM exam_session WHERE user_id = ? AND exam = ? ORDER BY attempt_number DESC LIMIT 1');
$latest_session->execute([$user_id, $exam_id]);
$attempt_info = $latest_session->fetch();

$display_attempt = 1;
$display_type = 'Main Sitting';

if ($existing_session) {
    $display_attempt = $existing_session['attempt_number'];
    $display_type = $existing_session['attempt_type'] === 'resit' ? 'Resit' : 'Main Sitting';
} elseif ($attempt_info) {
    // If starting a new session and a previous one exists, it will be attempt + 1
    $display_attempt = $attempt_info['attempt_number'] + 1;
    $display_type = 'Resit';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="asset/logo.jpg">
    <title>Exam Instructions</title>
    <!-- Modern Typography: Outfit from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-hover: #45a049;
            --secondary-color: #636e72;
            --secondary-hover: #2d3436;
            --bg-overlay: rgba(0, 0, 0, 0.4);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-main: #2d3436;
            --text-muted: #636e72;
            --accent-bg: #f8f9fa;
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

        .instruction-card {
            width: 100%;
            max-width: 1000px;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 30px 40px;
            box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.6s ease-out;
            position: relative;
        }

        .main-content-split {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 30px;
            margin-top: 20px;
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

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .user-info h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .logout-btn {
            background: #e74c3c;
            color: white !important;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.2);
        }

        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(231, 76, 60, 0.3);
        }

        h4 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            text-align: center;
        }

        .intro-p {
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-item {
            background: var(--accent-bg);
            padding: 15px 20px;
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .info-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-value {
            display: block;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .rules-section {
            text-align: left;
            margin-bottom: 25px;
        }

        .rules-section h5 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 10px;
            padding-left: 10px;
            border-left: 4px solid var(--primary-color);
        }

        .rules-list {
            list-style: none;
        }

        .rules-list li {
            position: relative;
            padding-left: 30px;
            margin-bottom: 12px;
            color: var(--text-main);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .rules-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-weight: 700;
        }

        .action-container {
            text-align: center;
            margin-top: 30px;
        }

        .start-btn {
            display: inline-block;
            padding: 16px 60px;
            background: var(--primary-color);
            color: white !important;
            border: none;
            border-radius: 16px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }

        .start-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px rgba(76, 175, 80, 0.4);
        }

        @media (max-width: 900px) {
            .main-content-split {
                grid-template-columns: 1fr;
            }

            .instruction-card {
                max-width: 600px;
                padding: 30px 25px;
            }

            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (max-width: 600px) {
            .header-flex {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="instruction-card">
        <div class="header-flex">
            <div class="user-info">
                <h3>Hello, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</h3>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <h4><?php echo htmlspecialchars($exam['title']); ?></h4>
        <p class="intro-p">Please review the instructions below before beginning your session.</p>

        <div class="main-content-split">
            <!-- Left Column: Guidelines & Action -->
            <div class="rules-column">
                <div class="rules-section">
                    <h5>Rules & Guidelines</h5>
                    <ul class="rules-list">
                        <li>Select the most appropriate answer for each question.</li>
                        <li>Time management is key – complete all questions within the allotted time.</li>
                        <li>Electronic devices (calculators, phones, etc.) are strictly prohibited.</li>
                        <li>Any violation of exam rules may lead to immediate disqualification.</li>
                        <li>If you encounter issues, raise your hand to signal an invigilator.</li>
                    </ul>
                </div>

                <div class="action-container">
                    <a href="#" id="startExamBtn" class="start-btn"><?php echo $button_text; ?></a>
                </div>
            </div>

            <!-- Right Column: Details Cards -->
            <div class="details-column">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Course Code</span>
                        <span class="info-value"><?php echo htmlspecialchars($exam['code']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Duration</span>
                        <span class="info-value"><?php echo $exam['time_allowed']; ?> Minutes</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Attempt Type</span>
                        <span class="info-value"><?php echo $display_type; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Attempt Number</span>
                        <span class="info-value">#<?php echo $display_attempt; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Questions</span>
                        <span class="info-value"><?php echo $exam['number_of_questions']; ?> MCQs</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="jquery/jquery-3.2.1.min.js"></script>
    <script src="swal/sweetalert2.all.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#startExamBtn').on('click', function (e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Loading Exam...',
                    text: 'Preparing your session, please wait.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });

                $.ajax({
                    url: 'start_exam.php',
                    method: 'POST',
                    dataType: 'json',
                    success: function (data) {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else if (data.error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.error
                            });
                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'Notice',
                                text: 'Your examination session has already been processed or completed.'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'index.php';
                                }
                            });
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Connection failed. Please check your internet and try again.', 'error');
                    }
                });
            });
        });
    </script>
</body>

</html>
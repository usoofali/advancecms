<?php
session_start();
require 'db.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$exam_id = $_SESSION['exam_id'];

$exam = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$exam->execute([$exam_id]);
$exam = $exam->fetch();

$username = $_SESSION['username'];
$session_stmt = $pdo->prepare('SELECT * FROM exam_session WHERE user_id = :user_id AND exam = :exam AND username = :username ORDER BY attempt_number DESC LIMIT 1');
$session_stmt->execute(['user_id' => $user_id, 'username' => $username, 'exam' => $exam_id]);
$session = $session_stmt->fetch();

if (! $session || $session['submit_status'] != 0) {
    header('Location: index.php?error=1');
    exit();
}

$stmt = $pdo->prepare('SELECT * FROM answers WHERE user_id = :user_id and exam = :exam');
$stmt->execute(['user_id' => $_SESSION['user_id'], 'exam' => $exam_id]);
$questions = $stmt->fetchAll();
$questionCount = count($questions);

$user_id = $_SESSION['user_id'];
$session_stmt = $pdo->prepare('SELECT * FROM exam_session WHERE user_id = :user_id AND exam = :exam ORDER BY attempt_number DESC LIMIT 1');
$session_stmt->execute(['user_id' => $user_id, 'exam' => $exam_id]);
$exam_session = $session_stmt->fetch(PDO::FETCH_ASSOC);
$remaining_time = strtotime($exam_session['stop_at']) - time();

// Set the remaining time for the exam
$remainingTime = ceil($remaining_time);

$exams = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$exams->execute([$_SESSION['exam_id']]);
$exams = $exams->fetch();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="asset/logo.jpg">
    <title>Exam Session - <?php echo htmlspecialchars($exams['code']); ?></title>
    <!-- Modern Typography: Outfit from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <script src="jquery/jquery-3.2.1.min.js"></script>
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-hover: #45a049;
            --secondary-bg: #f8f9fa;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-main: #2d3436;
            --text-muted: #636e72;
            --timer-bg: #fff5f5;
            --timer-text: #e03131;
            --selected-bg: #ebfbee;
            --selected-border: #40c057;
        }

        body {
            background-color: #f1f3f5;
            background-image: linear-gradient(rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.8)), url('asset/background.jpg');
            background-size: cover;
            background-attachment: fixed;
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
            /* Prevent body scroll */
            padding: 0;
            margin: 0;
        }

        .exam-app-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 15px;
            gap: 15px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Top Bar Styles - More Compact */
        .top-header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 10px 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--glass-border);
            flex-shrink: 0;
        }

        .user-meta h2 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
        }

        .user-meta p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .timer-box {
            background: var(--timer-bg);
            color: var(--timer-text);
            padding: 6px 15px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(224, 49, 49, 0.1);
        }

        .submit-btn {
            background: #ff922b;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: #f76707;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 146, 43, 0.3);
        }

        .calc-btn {
            background: #4dabf7;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            margin-right: 15px;
        }

        .calc-btn:hover {
            background: #339af0;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(77, 171, 247, 0.3);
            color: white;
        }

        /* Calculator Styles */
        .calculator-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            padding: 10px;
        }

        .calc-display {
            grid-column: span 4;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            text-align: right;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            min-height: 60px;
            word-break: break-all;
        }

        .calc-key {
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .calc-key:hover {
            background: #f1f3f5;
            transform: translateY(-1px);
        }

        .calc-key.operator {
            background: #e7f5ff;
            color: #228be6;
        }

        .calc-key.equals {
            background: var(--primary-color);
            color: white;
            grid-column: span 2;
        }

        .calc-key.clear {
            background: #fff0f0;
            color: #fa5252;
            grid-column: span 2;
        }

        /* Main Content Grid - Adjusted for Viewport Height */
        .exam-main-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 15px;
            flex-grow: 1;
            min-height: 0;
            /* Important for flex child with overflow */
        }

        .question-card {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            /* Scrollable question content if needed */
        }

        .q-number {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary-color);
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        #question {
            font-size: 1.15rem;
            font-weight: 600;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .options-list {
            display: grid;
            gap: 10px;
            flex-grow: 1;
        }

        .option-item {
            position: relative;
        }

        .option-item input {
            position: absolute;
            opacity: 0;
        }

        .option-label {
            display: block;
            padding: 12px 20px;
            background: var(--secondary-bg);
            border: 2px solid transparent;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .option-item input:checked+.option-label {
            background: var(--selected-bg);
            border-color: var(--selected-border);
            color: #2b8a3e;
        }

        .option-label:hover {
            background: #e9ecef;
        }

        .navigation-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f1f3f5;
            flex-shrink: 0;
        }

        .nav-nav-btn {
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-prev {
            background: #dee2e6;
            color: #495057;
        }

        .btn-next {
            background: var(--primary-color);
            color: white;
        }

        .btn-prev:hover {
            background: #ced4da;
        }

        .btn-next:hover {
            background: var(--primary-hover);
        }

        /* Side Navigation - Scrollable if many questions */
        .side-nav {
            background: #fff;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: sticky;
            top: 15px;
            /* Adjust as needed */
        }

        .side-nav h5 {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 15px;
            flex-shrink: 0;
            color: var(--text-main);
        }

        .question-map-container {
            overflow-y: auto;
            flex-grow: 1;
            padding: 10px;
            /* Extra room for highlights */
            margin: 0 -10px;
            /* Counteract side-nav padding for scrollbar */
        }

        .question-map {
            display: grid;
            grid-template-columns: repeat(9, 1fr);
            gap: 8px;
        }

        .map-btn {
            aspect-ratio: 1;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            background: #f1f3f5;
            color: #adb5bd;
            padding: 0;
        }

        .map-btn.answered {
            background: var(--primary-color);
            color: white;
        }

        .map-btn.current {
            box-shadow: 0 0 0 2px white, 0 0 0 4px var(--primary-color);
            z-index: 1;
        }

        #saving-indicator {
            position: absolute;
            top: 15px;
            right: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary-color);
            display: none;
        }

        @media (max-width: 992px) {
            body {
                overflow: auto;
                height: auto;
            }

            .exam-app-wrapper {
                height: auto;
            }

            .exam-main-grid {
                grid-template-columns: 1fr;
            }

            .side-nav {
                order: -1;
                position: static;
            }

            /* Move side nav to top on smaller screens */
            .question-map {
                grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            }
        }
    </style>
</head>

<body>
    <div class="exam-app-wrapper">
        <header class="top-header">
            <div class="user-meta">
                <h2><?php echo htmlspecialchars($_SESSION['fullname'].' ('.$_SESSION['username'].')'); ?></h2>
                <p><?php echo htmlspecialchars($exams['code'].': '.$exams['title']); ?></p>
            </div>

            <div class="timer-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                <span id="time">00:00:00</span>
            </div>

            <div class="d-flex align-items-center">
                <button type="button" class="calc-btn" data-bs-toggle="modal" data-bs-target="#calcModal">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect>
                        <line x1="8" y1="6" x2="16" y2="6"></line>
                        <line x1="16" y1="14" x2="16" y2="14"></line>
                        <line x1="16" y1="18" x2="16" y2="18"></line>
                        <line x1="12" y1="14" x2="12" y2="14"></line>
                        <line x1="12" y1="18" x2="12" y2="18"></line>
                        <line x1="8" y1="14" x2="8" y2="14"></line>
                        <line x1="8" y1="18" x2="8" y2="18"></line>
                    </svg>
                    Calculator
                </button>
                <button id="submit-exam" class="submit-btn text-uppercase">Submit</button>
            </div>
        </header>

        <main class="exam-main-grid">
            <section class="question-card">
                <div id="saving-indicator">
                    <span id="saving">Saving...</span>
                    <span id="nsaving" style="color: #e03131;">Retrying...</span>
                </div>

                <span id="question_no" class="q-number">Question 1</span>
                <div id="question">Loading...</div>

                <div id="options" class="options-list">
                    <!-- Dynamic Options -->
                </div>

                <div class="navigation-footer">
                    <button id="prev-question" class="nav-nav-btn btn-prev btn btn-light">Previous</button>
                    <button id="next-question" class="nav-nav-btn btn-next btn">Next Question</button>
                </div>
            </section>

            <aside class="side-nav">
                <h5>Navigation</h5>
                <div class="question-map-container">
                    <div id="question-map" class="question-map">
                        <?php foreach ($questions as $q) { ?>
                            <button id="q<?php echo $q['question_number']; ?>"
                                class="map-btn <?php echo $q['user_answer'] ? 'answered' : ''; ?>"
                                data-id="<?php echo $q['question_number']; ?>">
                                <?php echo $q['question_number']; ?>
                            </button>
                        <?php } ?>
                    </div>
                </div>
            </aside>
        </main>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-0"><img id="modalImage" src="" class="img-fluid rounded"></div>
            </div>
        </div>
    </div>

    <!-- Calculator Modal -->
    <div class="modal fade" id="calcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0"
                style="border-radius: 20px; background: var(--glass-bg); backdrop-filter: blur(10px);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="font-size: 1rem;">Scientific Calculator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="calc-display" class="calc-display">0</div>
                    <div class="calculator-grid">
                        <button class="calc-key clear" onclick="clearCalc()">C</button>
                        <button class="calc-key operator" onclick="appendCalc('/')">÷</button>
                        <button class="calc-key operator" onclick="appendCalc('*')">×</button>

                        <button class="calc-key" onclick="appendCalc('7')">7</button>
                        <button class="calc-key" onclick="appendCalc('8')">8</button>
                        <button class="calc-key" onclick="appendCalc('9')">9</button>
                        <button class="calc-key operator" onclick="appendCalc('-')">−</button>

                        <button class="calc-key" onclick="appendCalc('4')">4</button>
                        <button class="calc-key" onclick="appendCalc('5')">5</button>
                        <button class="calc-key" onclick="appendCalc('6')">6</button>
                        <button class="calc-key operator" onclick="appendCalc('+')">+</button>

                        <button class="calc-key" onclick="appendCalc('1')">1</button>
                        <button class="calc-key" onclick="appendCalc('2')">2</button>
                        <button class="calc-key" onclick="appendCalc('3')">3</button>
                        <button class="calc-key" onclick="calculateResult()">=</button>

                        <button class="calc-key" style="grid-column: span 2;" onclick="appendCalc('0')">0</button>
                        <button class="calc-key" onclick="appendCalc('.')">.</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="swal/sweetalert2.all.min.js"></script>
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        let remainingTime = <?php echo $remainingTime; ?>;
        const totalQuestions = <?php echo $questionCount; ?>;
        let currentQuestionId = 1;
        let isLoadingQuestion = false;

        function formatTime(s) {
            const h = String(Math.floor(s / 3600)).padStart(2, '0');
            const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
            const sec = String(s % 60).padStart(2, '0');
            return `${h}:${m}:${sec}`;
        }

        function updateTimer() {
            if (remainingTime <= 0) {
                $.post('submit_exam.php', { user_id: <?php echo $user_id; ?> }).done(() => window.location.href = 'completed.php');
            } else {
                $('#time').text(formatTime(remainingTime));
                remainingTime--;
            }
        }

        function loadQuestion(id) {
            if (isLoadingQuestion) return;
            isLoadingQuestion = true;
            currentQuestionId = id;
            $('.map-btn').removeClass('current');
            $(`.map-btn[data-id="${id}"]`).addClass('current');

            $.get('fetch_question.php', { id: id }, function (data) {
                $('#question_no').text('Question ' + data.question_number);
                $('#question').html(data.question);
                let opts = '';
                const labels = ['A', 'B', 'C', 'D'];
                [data.option1, data.option2, data.option3, data.option4].forEach((opt, i) => {
                    opts += `
                        <div class="option-item">
                            <input type="radio" id="opt${i}" name="answer" value="${i + 1}" ${data.user_answer == (i + 1) ? 'checked' : ''}>
                            <label class="option-label" for="opt${i}"><span class="fw-bold me-2">${labels[i]}.</span> ${opt}</label>
                        </div>`;
                });
                $('#options').html(opts);
                $('#prev-question').toggle(id > 1);
                $('#next-question').text(id == totalQuestions ? 'Final' : 'Next Question').removeClass('btn-warning btn-success').addClass(id == totalQuestions ? 'btn-warning' : 'btn-success');
            }, 'json').always(() => isLoadingQuestion = false);
        }

        $(document).ready(function () {
            updateTimer();
            setInterval(updateTimer, 1000);
            loadQuestion($(".map-btn").first().data("id"));

            // --- Optimized Connectivity & Downtime Monitor ---
            let isOffline = false;
            let downtimeStart = null;
            let offlineSwal = null;
            let restorationTimer = null;
            let downtimeCounter = null;

            // Global AJAX handler for connectivity tracking
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (xhr.status === 0 || xhr.status >= 500) {
                    handleConnectionFailure();
                } else if (isOffline && xhr.status === 200) {
                    handleConnectionRestoration();
                }
            });

            function handleConnectionFailure() {
                if (!isOffline) {
                    isOffline = true;
                    downtimeStart = new Date();
                    
                    offlineSwal = Swal.fire({
                        title: 'Network Connection Lost',
                        html: `
                            <div class="p-3">
                                <p class="mb-2">The server is currently unreachable.</p>
                                <div class="bg-light p-3 rounded-4 mb-3 border">
                                    <span class="text-muted d-block small text-uppercase fw-bold">Downtime Recorded</span>
                                    <h2 class="fw-bold text-danger mb-0"><span id="downtime-clock">0</span>s</h2>
                                </div>
                                <b class="text-danger">DO NOT REFRESH THIS PAGE.</b><br>
                                Your time is being paused and will be added back automatically.
                            </div>
                        `,
                        icon: 'warning',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        backdrop: `rgba(0,0,123,0.4)`
                    });

                    // Live clock update
                    downtimeCounter = setInterval(function() {
                        const now = new Date();
                        const diff = Math.round((now - downtimeStart) / 1000);
                        const clockEl = document.getElementById('downtime-clock');
                        if (clockEl) clockEl.innerText = diff;
                    }, 1000);

                    // Start a slow background ping ONLY when offline to detect restoration
                    restorationTimer = setInterval(function() {
                        $.get('check_connectivity.php').done(handleConnectionRestoration);
                    }, 10000); 
                }
            }

            function handleConnectionRestoration() {
                if (isOffline) {
                    isOffline = false;
                    clearInterval(restorationTimer);
                    clearInterval(downtimeCounter);
                    
                    const downtimeEnd = new Date();
                    const diffSeconds = Math.round((downtimeEnd - downtimeStart) / 1000);
                    
                    if (offlineSwal) offlineSwal.close();

                    Swal.fire({
                        title: 'Connection Restored!',
                        html: `We detected a downtime of <b>${diffSeconds} seconds</b>.<br>Applying time compensation...`,
                        icon: 'success',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    // Push compensation to server
                    $.post('check_connectivity.php', { increment_seconds: diffSeconds }, function(res) {
                        if (res.success) {
                            Swal.fire({
                                title: 'Time Compensated',
                                text: `${diffSeconds} seconds have been added to your exam timer.`,
                                icon: 'success',
                                timer: 3000
                            }).then(() => {
                                window.location.reload(); 
                            });
                        }
                    });
                    downtimeStart = null;
                }
            }
            // --------------------------------------------------

            $('.map-btn').click(function () { loadQuestion($(this).data('id')); });

            $(document).on('change', 'input[name="answer"]', function () {
                $('#saving').show(); $('#nsaving').hide();
                $.post('save_answer.php', { exam: <?php echo $exam_id; ?>, user_id: <?php echo $user_id; ?>, question_id: currentQuestionId, answer: $(this).val() })
                    .done(res => {
                        const data = JSON.parse(res);
                        if (data.success) {
                            $(`.map-btn[data-id="${currentQuestionId}"]`).addClass('answered');
                            $('#saving').fadeOut();
                        } else $('#nsaving').show();
                    }).fail(() => $('#nsaving').show());
            });

            $('#next-question').click(() => { if (currentQuestionId < totalQuestions) loadQuestion(currentQuestionId + 1); });
            $('#prev-question').click(() => { if (currentQuestionId > 1) loadQuestion(currentQuestionId - 1); });

            $('#submit-exam').click(() => {
                Swal.fire({ title: 'Submit Examination?', text: "You won't be able to revert this!", icon: 'question', showCancelButton: true, confirmButtonColor: '#4CAF50', confirmButtonText: 'Yes, Submit Now' }).then(v => {
                    if (v.isConfirmed) {
                        Swal.fire({ title: 'Submitting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        $.post('submit_exam.php', { user_id: <?php echo $user_id; ?> }).done(() => {
                            Swal.fire('Success', 'Submitted successfully!', 'success').then(() => window.location.href = 'completed.php');
                        });
                    }
                });
            });
        });

        // Calculator Logic
        let calcExpression = '';
        function updateCalcDisplay() {
            document.getElementById('calc-display').innerText = calcExpression || '0';
        }
        function appendCalc(val) {
            calcExpression += val;
            updateCalcDisplay();
        }
        function clearCalc() {
            calcExpression = '';
            updateCalcDisplay();
        }
        function calculateResult() {
            try {
                // Use Function instead of eval for slightly better safety
                calcExpression = String(new Function('return ' + calcExpression)());
                updateCalcDisplay();
            } catch (e) {
                calcExpression = 'Error';
                updateCalcDisplay();
                setTimeout(clearCalc, 1500);
            }
        }
    </script>
</body>

</html>
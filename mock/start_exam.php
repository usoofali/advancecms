<?php

session_start();
require 'db.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (! isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

try {
    $pdo->beginTransaction();

    $user_id = $_SESSION['user_id'];
    $exam_id = $_SESSION['exam_id'];
    $username = $_SESSION['username'];

    if (! $exam_id) {
        throw new Exception('No exam session found.');
    }

    // Fetch exam details
    $exam_stmt = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
    $exam_stmt->execute([$exam_id]);
    $exam = $exam_stmt->fetch();

    if (! $exam) {
        throw new Exception('Exam not found.');
    }

    // Check for the latest session for this student + exam
    $session_stmt = $pdo->prepare('SELECT * FROM exam_session WHERE user_id = :user_id AND exam = :exam AND username = :username ORDER BY attempt_number DESC LIMIT 1');
    $session_stmt->execute(['user_id' => $user_id, 'username' => $username, 'exam' => $exam_id]);
    $exam_session = $session_stmt->fetch(PDO::FETCH_ASSOC);

    // Determine if this is a brand-new attempt or a resit
    $canStartNew = false;
    $attemptNumber = 1;
    $attemptType = 'normal';

    if (! $exam_session) {
        // First-ever attempt
        $canStartNew = true;
    } elseif ($exam_session['submit_status'] == 0 && time() < strtotime($exam_session['stop_at'])) {
        // Active session still in progress — resume it
        $pdo->commit();
        echo json_encode(['redirect' => 'exam.php']);
        exit();
    } elseif ($exam_session['submit_status'] == 1 && $exam_session['is_synced'] == 1) {
        // Previous attempt was submitted AND synced — allow a resit
        $canStartNew = true;
        $attemptNumber = ((int) $exam_session['attempt_number']) + 1;
        $attemptType = 'resit';
    } else {
        // Session expired or submitted but not yet synced — block
        $pdo->rollBack();
        echo json_encode(['error' => 'Session expired or already submitted. Awaiting sync before resit is allowed.']);
        exit();
    }

    if ($canStartNew) {
        // Fetch questions for the new session
        $q_stmt = $pdo->prepare('SELECT * FROM question WHERE exam = :exam');
        $q_stmt->execute(['exam' => $exam_id]);
        $questions = $q_stmt->fetchAll();
        $total_q = count($questions);

        $now = time();
        $startedAt = date('Y-m-d H:i:s', $now);
        $stopAt = date('Y-m-d H:i:s', $now + (60 * (int) $exam['time_allowed']));

        $insert_session = $pdo->prepare('INSERT INTO exam_session (user_id, username, exam, attempt_number, attempt_type, started_at, stop_at, submit_status, total_score, percent_score, total_questions, attempted) VALUES (:user_id, :username, :exam, :attempt_number, :attempt_type, :started_at, :stop_at, 0, 0, 0, :total_q, 0)');
        $insert_session->execute([
            'user_id' => $user_id,
            'username' => $username,
            'exam' => $exam_id,
            'attempt_number' => $attemptNumber,
            'attempt_type' => $attemptType,
            'started_at' => $startedAt,
            'stop_at' => $stopAt,
            'total_q' => $total_q,
        ]);

        shuffle($questions);

        // Clear any previous answers for this user + exam (resit scenario)
        if ($attemptType === 'resit') {
            $pdo->prepare('DELETE FROM answers WHERE user_id = ? AND exam = ?')->execute([$user_id, $exam_id]);
        }

        // Prep questions for this user's attempt
        $insert_answer = $pdo->prepare('INSERT INTO answers (question_number, question, option1, option2, option3, option4, answer, user_answer, user_id, exam) VALUES (:question_number, :question, :option1, :option2, :option3, :option4, :answer, 0, :user_id, :exam)');

        foreach ($questions as $index => $q) {
            // Create a temporary array of options with their original 'correct' status
            $opts = [
                ['text' => $q['option1'], 'is_correct' => $q['answer'] == 1],
                ['text' => $q['option2'], 'is_correct' => $q['answer'] == 2],
                ['text' => $q['option3'], 'is_correct' => $q['answer'] == 3],
                ['text' => $q['option4'], 'is_correct' => $q['answer'] == 4],
            ];

            // Shuffle the options
            shuffle($opts);

            // Find the new index of the correct answer
            $newCorrect = 1;
            foreach ($opts as $oIdx => $o) {
                if ($o['is_correct']) {
                    $newCorrect = $oIdx + 1;
                    break;
                }
            }

            $insert_answer->execute([
                'question_number' => $index + 1,
                'question' => $q['question'],
                'option1' => $opts[0]['text'],
                'option2' => $opts[1]['text'],
                'option3' => $opts[2]['text'],
                'option4' => $opts[3]['text'],
                'answer' => $newCorrect,
                'user_id' => $user_id,
                'exam' => $exam_id,
            ]);
        }

        $pdo->commit();
        echo json_encode(['redirect' => 'exam.php']);
        exit();
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

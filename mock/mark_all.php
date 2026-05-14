<?php

session_start();
require 'db.php';

$stmt = $pdo->query('SELECT * FROM exam_session WHERE submit_status = 1');
$stmt->execute();
$users = $stmt->fetchAll();

$count = 0;
foreach ($users as $index => $user) {
    $count++;
    $user_id = $user['user_id'];
    $exam = $user['exam'];
    $username = $user['username'];
    $session_id = $user['session_id'];

    $query = 'SELECT user_answer, answer FROM answers WHERE user_id = :user_id and exam = :exam';
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user['user_id'], 'exam' => $user['exam']]);

    $score_counter = 0;
    $total_questions = 0;
    $attempted = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['user_answer'] == $row['answer']) {
            $score_counter++;
        }

        if ($row['user_answer'] != '0') {
            $attempted++;
        }
        $total_questions++;
    }

    if ($total_questions > 0) {
        $percent_score = round(($score_counter / $total_questions) * 70);
    } else {
        $percent_score = 0;
    }
    if ($percent_score >= 70) {
        echo "<p>$username has ($score_counter Div $total_questions) * 70</p>";
    }

    $update_query = 'UPDATE exam_session SET submit_status = 1, total_score = ?, percent_score = ?, total_questions = ? , attempted = ? WHERE user_id = ? and exam = ?';
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([$score_counter, $percent_score, $total_questions, $attempted, $user['user_id'], $user['exam']]);

}

echo "<p>$count exams marked</p>";

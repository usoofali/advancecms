<?php

session_start();

// Include database connection
require 'db.php'; // Adjust the path as needed

// Check if user is logged in
if (! isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Redirect to login if not authenticated
    exit();
}
$exam_id = $_SESSION['exam_id'];
// Fetch user's answers from the answers
$user_id = $_SESSION['user_id'];
$query = 'SELECT question_answer_id, user_answer, answer FROM answers WHERE user_id = :user_id and exam = :exam';
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $user_id, 'exam' => $exam_id]);

$score_counter = 0;
$total_questions = 0;
$attempted = 0;

// Iterate through the fetched answers and calculate the score
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['user_answer'] === $row['answer']) {
        $score_counter++;
    }
    if ($row['user_answer'] != '0') {
        $attempted++;
    }
    $total_questions++;
}

if ($total_questions > 0) {
    $percent_score = ($score_counter / $total_questions) * 100;
} else {
    $percent_score = 0;
}

$update_query = 'UPDATE exam_session SET submit_status = 1, total_score = ?, percent_score = ?, total_questions = ? , attempted = ? WHERE user_id = ? AND exam = ? AND submit_status = 0';
$update_stmt = $pdo->prepare($update_query);
$update_stmt->execute([$score_counter, $percent_score, $total_questions, $attempted, $user_id, $exam_id]);

// Redirect to the completed page
header('Location: completed.php');
exit();

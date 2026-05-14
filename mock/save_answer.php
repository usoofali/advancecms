<?php

session_start();
require 'db.php';
try {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $userId = $_POST['user_id'];
        $questionId = $_POST['question_id'];
        $answer = $_POST['answer'];
        $exam = $_POST['exam'];
        $stmt = $pdo->prepare('UPDATE answers SET user_answer = :user_answer WHERE question_number = :question_number AND user_id = :user_id AND exam = :exam');
        $stmt->execute(['user_answer' => $answer, 'question_number' => $questionId, 'user_id' => $userId, 'exam' => $exam]);
        $response = ['success' => true];
        echo json_encode($response);
    } else {
        $response = ['success' => false];
        echo json_encode($response);

    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e];
    echo json_encode($response);
}

<?php

session_start();
require 'db.php';

if (! isset($_GET['id'])) {
    exit('Invalid request.');
}
$exam_id = $_SESSION['exam_id'];

$stmt = $pdo->prepare('SELECT * FROM answers WHERE question_number = :id AND user_id = :user_id AND exam = :exam');
$stmt->execute(['id' => $_GET['id'], 'user_id' => $_SESSION['user_id'], 'exam' => $exam_id]);
$question = $stmt->fetch();

echo json_encode($question);

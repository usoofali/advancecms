<?php

require 'admin_auth.php';

if (isset($_GET['id'])) {
    $exam_id = $_GET['id'];

    // Fetch exam info for filename
    $stmt = $pdo->prepare('SELECT code, title FROM exams WHERE id = ?');
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();

    if (! $exam) {
        exit('Examination not found.');
    }

    // Fetch questions
    $stmt = $pdo->prepare('SELECT question, option1, option2, option3, option4, answer FROM question WHERE exam = ?');
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    $filename = 'Questions_'.str_replace(' ', '_', $exam['code']).'_'.date('Y-m-d').'.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Output the column headings
    fputcsv($output, ['Question', 'Option 1', 'Option 2', 'Option 3', 'Option 4', 'Correct Answer']);

    // Loop through the data and output it
    foreach ($questions as $q) {
        fputcsv($output, [
            $q['question'],
            $q['option1'],
            $q['option2'],
            $q['option3'],
            $q['option4'],
            $q['answer'],
        ]);
    }

    fclose($output);
    exit();
} else {
    exit('No examination specified.');
}

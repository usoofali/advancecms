<?php

require 'admin_auth.php';

// Get filters from GET request
$exam_filter = $_GET['exam'] ?? '';
$level_filter = $_GET['level'] ?? '';

// Build Query
$query = '
    SELECT 
        s.username, 
        s.ca_score, 
        s.total_score
    FROM exam_session s
    JOIN exams e ON s.exam = e.id
    WHERE s.submit_status = 1
';

$params = [];

if (! empty($exam_filter)) {
    $query .= ' AND e.code = ?';
    $params[] = $exam_filter;
}

if (! empty($level_filter)) {
    $query .= ' AND e.level = ?';
    $params[] = $level_filter;
}

$query .= ' ORDER BY s.username ASC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Empty Export
if (empty($results)) {
    exit('No records found to export.');
}

// Set Headers for CSV
$filename = 'Results_Export_'.date('Y-m-d_H-i').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// Create CSV stream
$output = fopen('php://output', 'w');

// Headers
fputcsv($output, ['Username', 'CA Score', 'Exam Score']);

// Data Rows
foreach ($results as $r) {
    fputcsv($output, [
        $r['username'],
        $r['ca_score'],
        $r['total_score'],
    ]);
}

fclose($output);
exit();

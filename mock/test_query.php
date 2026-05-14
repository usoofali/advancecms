<?php

require 'db.php';
try {
    $results = $pdo->query('
        SELECT 
            s.*, 
            u.fullname, 
            e.code as exam_code, 
            e.title as exam_title,
            e.level as exam_level
        FROM exam_session s
        JOIN users u ON s.user_id = u.user_id
        JOIN exams e ON s.exam = e.id
        WHERE s.submit_status = 1
        ORDER BY s.started_at DESC
        LIMIT 1
    ')->fetchAll();
    echo 'Success!';
} catch (PDOException $e) {
    echo 'Error: '.$e->getMessage();
}

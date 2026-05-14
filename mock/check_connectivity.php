<?php

// mock/check_connectivity.php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (! isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$exam_id = $_SESSION['exam_id'];

// Action: Ping (Simple connectivity check)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'message' => 'Connected']);
    exit();
}

// Action: Increment Time (Compensate for downtime)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['increment_seconds'])) {
    $seconds = (int) $_POST['increment_seconds'];

    if ($seconds <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid duration']);
        exit();
    }

    try {
        // Update the stop_at time for the current session
        $stmt = $pdo->prepare('
            UPDATE exam_session 
            SET stop_at = DATE_ADD(stop_at, INTERVAL ? SECOND) 
            WHERE user_id = ? AND exam = ? AND submit_status = 0
        ');
        $stmt->execute([$seconds, $user_id, $exam_id]);

        echo json_encode([
            'success' => true,
            'message' => "Successfully added $seconds seconds to the exam timer.",
            'added_seconds' => $seconds,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: '.$e->getMessage()]);
    }
    exit();
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request']);

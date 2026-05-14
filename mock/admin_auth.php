<?php

session_start();
require 'db.php';

// Security Check: Only Admin can access
if (! isset($_SESSION['user_id']) || ! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Check database directly if session role is missing (first time login)
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user && $user['role'] === 'admin') {
            $_SESSION['role'] = 'admin';
        } else {
            header('Location: admin.php?error=unauthorized');
            exit();
        }
    } else {
        header('Location: admin.php');
        exit();
    }
}

$current_page = basename($_SERVER['PHP_SELF']);

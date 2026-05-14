<?php

date_default_timezone_set('Africa/Lagos');
$host = 'localhost'; // Your database host
$db = 'mock'; // Your database name
$user = 'root'; // Your database username
$pass = '@Gusau00'; // Your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connection failed: '.$e->getMessage();
}

<?php

$servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'mock';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    exit('Connection failed: '.$conn->connect_error);
}

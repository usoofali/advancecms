<?php

require 'db.php';
function printColumns($table, $pdo)
{
    echo "Columns for $table:\n";
    $stmt = $pdo->query("DESCRIBE $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '- '.$row['Field']."\n";
    }
    echo "\n";
}
printColumns('users', $pdo);
printColumns('exams', $pdo);
printColumns('exam_session', $pdo);

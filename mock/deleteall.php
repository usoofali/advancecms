<?php include 'config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Questions</title>
    <style>
        .container { 
            max-width: 500px; margin: 50px auto; text-align: center; 
        }
        form { 
            margin: 20px; padding: 20px; border: 1px solid #ddd; 
            }
        nav { 
            margin: 20px; 
        }
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            width: 100%;
            padding: 15px 30px;
            /* Increased padding for larger button */
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #3e8e41;
        }
    </style>
</head>
<body>
    <div class="container">

        <form action="upload.php" method="post" onsubmit="return confirm('Delete ALL test sessions?')">
            <button type="submit" name="deleteAll" style="background-color: #ff4444; color: white;">
                Delete All Test Sessions
            </button>
        </form>

        <?php

        // Handle delete all
        if (isset($_POST['deleteAll'])) {

            $conn->query('TRUNCATE TABLE answers');
            $conn->query('TRUNCATE TABLE exam_session');
            echo '<p>All questions deleted!</p>';
        }
?>
    </div>
</body>

</html>
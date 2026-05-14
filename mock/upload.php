<?php
require 'admin_auth.php';

// Handle CSV upload (MUST HAPPEN BEFORE LAYOUT)
$upload_success = null;
$upload_error = null;

if (isset($_POST['upload'])) {
    if ($_FILES['csvFile']['error'] == UPLOAD_ERR_OK) {
        try {
            $file = $_FILES['csvFile']['tmp_name'];
            $handle = fopen($file, 'r');

            $dept_id = $_POST['dept'];
            $time = $_POST['time'];
            $date = $_POST['date'];
            $questions_count = $_POST['questions'];
            $level = $_POST['level'];
            $course_parts = explode('-', $_POST['course']);
            $code = trim($course_parts[0]);
            $title = isset($course_parts[1]) ? trim($course_parts[1]) : '';

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO exams (code, title, dept, level, description, time_allowed, number_of_questions, date) 
                                 VALUES (?, ?, ?, ?, 'Answer all', ?, ?, ?)");
            $stmt->execute([$code, $title, $dept_id, $level, $time, $questions_count, $date]);
            $exam_id = $pdo->lastInsertId();

            $count = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($data) >= 6) {
                    $count++;
                    if (count($data) == 6) {
                        $question = $data[0];
                        $option1 = $data[1];
                        $option2 = $data[2];
                        $option3 = $data[3];
                        $option4 = $data[4];
                        $answer = (int) $data[5];
                    } else {
                        $question = $data[0].$data[1];
                        $option1 = $data[2];
                        $option2 = $data[3];
                        $option3 = $data[4];
                        $option4 = $data[5];
                        $answer = (int) $data[6];
                    }

                    $q_stmt = $pdo->prepare('INSERT INTO question (question, option1, option2, option3, option4, answer, exam) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $q_stmt->execute([$question, $option1, $option2, $option3, $option4, $answer, $exam_id]);
                }
            }
            $pdo->commit();
            fclose($handle);
            $upload_success = "Successfully processed $count questions for $code.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $upload_error = $e->getMessage();
        }
    }
}

// Include Layout
require 'admin_layout.php';

// Show Alerts if any
if ($upload_success) {
    echo "<script>$(document).ready(function() { Swal.fire('Success', '$upload_success', 'success'); });</script>";
}
if ($upload_error) {
    echo "<script>$(document).ready(function() { Swal.fire('Error', '".addslashes($upload_error)."', 'error'); });</script>";
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Upload Questions</h2>
        <p>Prepare and upload examination questions via CSV.</p>
    </div>
</div>

<div class="glass-card mx-auto" style="max-width: 700px;">
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <div class="form-group mb-3">
            <label class="form-label">Exam Level</label>
            <select name="level" required class="form-select border-0 shadow-sm py-2" style="border-radius: 12px;">
                <option value=''>Select Level</option>
                <option value='100L'>100L</option>
                <option value='200L'>200L</option>
                <option value='300L'>300L</option>
            </select>
        </div>

        <div class="form-group mb-3">
            <label class="form-label">Program / Department</label>
            <select name="dept" id="dept" required class="form-select border-0 shadow-sm py-2"
                style="border-radius: 12px;">
                <option value=''>Select Program</option>
                <option value='1'>CHEW</option>
                <option value='2'>HIM</option>
                <option value='3'>MLT</option>
                <option value='4'>PT</option>
            </select>
        </div>

        <div class="form-group mb-3">
            <label class="form-label">Course</label>
            <select name="course" id="course" required class="form-select border-0 shadow-sm py-2"
                style="border-radius: 12px;">
                <option value="">-- Select Course --</option>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Duration (Minutes)</label>
                <input type="number" name="time" placeholder="e.g. 60" required
                    class="form-control border-0 shadow-sm py-2" style="border-radius: 12px;">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Question Count</label>
                <input type="number" name="questions" placeholder="e.g. 50" required
                    class="form-control border-0 shadow-sm py-2" style="border-radius: 12px;">
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="form-label">Date of Exam</label>
            <input type="date" name="date" required class="form-control border-0 shadow-sm py-2"
                style="border-radius: 12px;">
        </div>

        <div class="form-group mb-3">
            <label class="form-label">CSV File</label>
            <input type="file" name="csvFile" accept=".csv" required class="form-control border-0 shadow-sm py-2"
                style="border-radius: 12px;">
            <p class="small text-muted mt-2 text-center">Format: question, option1, option2, option3, option4,
                correct_index (1-4)</p>
        </div>

        <button type="submit" name="upload" class="btn btn-primary w-100 py-3 fw-bold rounded-4 shadow-sm">
            Process & Upload Exam
        </button>
    </form>
</div>

</main>
</div>
<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {
        $('#dept').on('change', function () {
            var dept = this.value;
            fetch('get_courses.php?dept=' + dept)
                .then(response => response.json())
                .then(data => {
                    var courseSelect = document.getElementById('course');
                    courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
                    if (data.length === 0) return;
                    data.forEach(function (course) {
                        var option = document.createElement('option');
                        option.value = course.name;
                        option.text = course.name;
                        courseSelect.appendChild(option);
                    });
                });
        });
    });
</script>
</body>

</html>
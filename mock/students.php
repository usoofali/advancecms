<?php
require 'admin_auth.php';

// Handle student CSV upload
if (isset($_POST['upload']) && isset($_FILES['csvFile'])) {
    $level = $_POST['level'];
    $dept = $_POST['dept'];
    $file = $_FILES['csvFile']['tmp_name'];

    if ($_FILES['csvFile']['size'] > 0) {
        $handle = fopen($file, 'r');
        $success_count = 0;
        $error_count = 0;

        // Prepare statements
        $check_stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
        $insert_stmt = $pdo->prepare("INSERT INTO users (username, fullname, password, level, dept, role) VALUES (?, ?, ?, ?, ?, 'student')");
        $update_stmt = $pdo->prepare('UPDATE users SET fullname = ?, password = ?, level = ?, dept = ? WHERE username = ?');

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            // Expected Format: username, fullname, password
            if (count($data) < 3) {
                continue;
            }

            $username = trim($data[0]);
            $fullname = trim($data[1]);
            $password = strtoupper(trim($data[2])); // Standardizing to uppercase if needed

            if (empty($username)) {
                continue;
            }

            $check_stmt->execute([$username]);
            if ($check_stmt->fetch()) {
                // Update existing
                $update_stmt->execute([$fullname, $password, $level, $dept, $username]);
            } else {
                // Insert new
                $insert_stmt->execute([$username, $fullname, $password, $level, $dept]);
            }
            $success_count++;
        }
        fclose($handle);
        $upload_result = ['success' => true, 'count' => $success_count];
    } else {
        $upload_result = ['success' => false, 'message' => 'Empty file uploaded.'];
    }
}

// Include Layout
require 'admin_layout.php';

if (isset($upload_result)) {
    if ($upload_result['success']) {
        $msg = $upload_result['count'].' students processed successfully.';
        echo "<script>$(document).ready(function() { Swal.fire('Success!', '$msg', 'success'); });</script>";
    } else {
        $msg = $upload_result['message'];
        echo "<script>$(document).ready(function() { Swal.fire('Error!', '$msg', 'error'); });</script>";
    }
}

if (isset($deleted_all)) {
    echo "<script>$(document).ready(function() { Swal.fire('Cleared!', 'All test sessions and answers have been deleted.', 'success'); });</script>";
}

// Fetch Departments
$departments = $pdo->query('SELECT * FROM dept ORDER BY code ASC')->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h2>Student Management</h2>
        <p>Register students and manage examination attempts.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="glass-card mb-4">
            <h5 class="fw-bold mb-4">Upload Student List</h5>
            <form action="students.php" method="post" enctype="multipart/form-data">
                <div class="form-group mb-3">
                    <label class="form-label">Level</label>
                    <select name="level" class="form-select border-0 shadow-sm py-2" style="border-radius: 12px;"
                        required>
                        <option value='100L'>100L</option>
                        <option value='200L'>200L</option>
                        <option value='300L'>300L</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Department</label>
                    <select name="dept" class="form-select border-0 shadow-sm py-2" style="border-radius: 12px;"
                        required>
                        <?php foreach ($departments as $d) { ?>
                            <option value="<?php echo $d['id']; ?>">
                                <?php echo htmlspecialchars($d['code']); ?>
                            </option>
                        <?php } ?>
                        <?php if (empty($departments)) { ?>
                            <option value="">No departments found</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group mb-4">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csvFile" class="form-control border-0 shadow-sm py-2"
                        style="border-radius: 12px;" accept=".csv" required>
                    <p class="small text-muted mt-2 text-center">Format: username, fullname, password</p>
                </div>
                <button type="submit" name="upload" class="btn btn-primary w-100 py-3 fw-bold rounded-4 shadow-sm">
                    Upload Students
                </button>
            </form>
        </div>
    </div>
</div>

</main>
</div>
<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {
        $('.delete-all-form').on('click', '.btn-outline-danger', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you absolutely sure?',
                text: "This will wipe ALL student progress, answer logs, and active sessions. There is no recovery!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e03131',
                confirmButtonText: 'Yes, Wipe Everything'
            }).then((result) => {
                if (result.isConfirmed) {
                    $(this).closest('form').submit();
                }
            });
        });
    });
</script>
</body>

</html>
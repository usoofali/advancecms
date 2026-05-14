<?php
require 'admin_auth.php';

// Fetch Stats (Logic BEFORE layout)
$exams_count = $pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn();
$active_sessions = $pdo->query('SELECT COUNT(*) FROM exam_session WHERE submit_status = 0 AND stop_at > NOW()')->fetchColumn();
$students_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

// Fetch Recent Exams
$recent_exams = $pdo->query('SELECT * FROM exams ORDER BY date DESC LIMIT 5')->fetchAll();

// Include Layout
require 'admin_layout.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2>Admin Dashboard</h2>
        <p>Welcome back, Administrator. Here's what's happening today.</p>
    </div>
</div>

<div class="row mb-5">
    <div class="col-md-4">
        <div class="glass-card text-center">
            <h3 class="display-4 fw-bold text-primary mb-1"><?php echo $exams_count; ?></h3>
            <p class="text-muted fw-600 mb-0">Total Examinations</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card text-center">
            <h3 class="display-4 fw-bold text-success mb-1"><?php echo $active_sessions; ?></h3>
            <p class="text-muted fw-600 mb-0">Active Sessions</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card text-center">
            <h3 class="display-4 fw-bold text-info mb-1"><?php echo $students_count; ?></h3>
            <p class="text-muted fw-600 mb-0">Total Students</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="glass-card mb-4">
            <h5 class="fw-bold mb-4">Recent Examinations</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_exams as $exam) { ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($exam['code']); ?></td>
                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($exam['date'])); ?></td>
                                <td>
                                    <span
                                        class="badge rounded-pill <?php echo $exam['status'] == 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $exam['status'] == 0 ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="manage_exams.php" class="btn btn-sm btn-outline-primary fw-bold">View All Exams</a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="glass-card h-100">
            <h5 class="fw-bold mb-4">Quick Actions</h5>
            <div class="d-grid gap-3">
                <a href="upload.php" class="btn btn-light py-3 border text-start d-flex align-items-center gap-3">
                    <div class="bg-primary text-white p-2 rounded-3">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <span class="fw-600">Upload Questions</span>
                </a>
                <a href="manage_students.php"
                    class="btn btn-light py-3 border text-start d-flex align-items-center gap-3">
                    <div class="bg-info text-white p-2 rounded-3">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <path d="M20 8v6M23 11h-6"></path>
                        </svg>
                    </div>
                    <span class="fw-600">Manage Students</span>
                </a>
                <a href="manage_sessions.php"
                    class="btn btn-light py-3 border text-start d-flex align-items-center gap-3">
                    <div class="bg-success text-white p-2 rounded-3">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
                        </svg>
                    </div>
                    <span class="fw-600">Monitor Sessions</span>
                </a>
                <a href="sync_manager.php"
                    class="btn btn-light py-3 border text-start d-flex align-items-center gap-3">
                    <div class="bg-warning text-white p-2 rounded-3">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z"></path>
                            <path d="M12 7v5l3 3"></path>
                        </svg>
                    </div>
                    <span class="fw-600">CMS Sync Hub</span>
                </a>
            </div>
        </div>
    </div>
</div>

</main>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>
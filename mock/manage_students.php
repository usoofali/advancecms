<?php
require 'admin_auth.php';

// Handle individual student registration
if (isset($_POST['add_student'])) {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $password = strtoupper(trim($_POST['password']));
    $level = $_POST['level'];
    $dept = $_POST['dept'];

    if (! empty($username) && ! empty($fullname) && ! empty($password)) {
        $check = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            $student_msg = ['success' => false, 'message' => 'Username/Admission number already exists.'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, fullname, password, level, dept, role) VALUES (?, ?, ?, ?, ?, 'student')");
            $stmt->execute([$username, $fullname, $password, $level, $dept]);
            $student_msg = ['success' => true, 'message' => 'Student registered successfully.'];
        }
    } else {
        $student_msg = ['success' => false, 'message' => 'Please fill all required fields.'];
    }
}

// Handle student edit
if (isset($_POST['edit_student'])) {
    $user_id = $_POST['user_id'];
    $fullname = trim($_POST['fullname']);
    $password = strtoupper(trim($_POST['password']));
    $level = $_POST['level'];
    $dept = $_POST['dept'];

    if (! empty($fullname) && ! empty($password)) {
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, password = ?, level = ?, dept = ? WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$fullname, $password, $level, $dept, $user_id]);
        $student_msg = ['success' => true, 'message' => 'Student updated successfully.'];
    } else {
        $student_msg = ['success' => false, 'message' => 'Please fill all required fields.'];
    }
}

// Handle student CSV upload
if (isset($_POST['upload']) && isset($_FILES['csvFile'])) {
    $level = $_POST['level'];
    $dept = $_POST['dept'];
    $file = $_FILES['csvFile']['tmp_name'];

    if ($_FILES['csvFile']['size'] > 0) {
        $handle = fopen($file, 'r');
        $success_count = 0;

        // Prepare statements
        $check_stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
        $insert_stmt = $pdo->prepare("INSERT INTO users (username, fullname, password, level, dept, role) VALUES (?, ?, ?, ?, ?, 'student')");
        $update_stmt = $pdo->prepare('UPDATE users SET fullname = ?, password = ?, level = ?, dept = ? WHERE username = ?');

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($data) < 3) {
                continue;
            }

            $username = trim($data[0]);
            $fullname = trim($data[1]);
            $password = strtoupper(trim($data[2]));

            if (empty($username)) {
                continue;
            }

            $check_stmt->execute([$username]);
            if ($check_stmt->fetch()) {
                $update_stmt->execute([$fullname, $password, $level, $dept, $username]);
            } else {
                $insert_stmt->execute([$username, $fullname, $password, $level, $dept]);
            }
            $success_count++;
        }
        fclose($handle);
        $student_msg = ['success' => true, 'message' => $success_count.' students processed via CSV.'];
    }
}

// Handle delete student
if (isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
    $stmt->execute([$_POST['delete_id']]);
    $student_msg = ['success' => true, 'message' => 'Student record deleted.'];
}

// Fetch Departments
$departments = $pdo->query('SELECT * FROM dept ORDER BY code ASC')->fetchAll();

// Fetch all students
$students = $pdo->query("
    SELECT u.*, d.code as dept_code 
    FROM users u 
    LEFT JOIN dept d ON u.dept = d.id 
    WHERE u.role = 'student' 
    ORDER BY u.username ASC
")->fetchAll();

require 'admin_layout.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div class="page-title">
        <h2>Student Management</h2>
        <p>Manage individual registrations and bulk uploads.</p>
    </div>
</div>

<!-- Filters -->
<div class="glass-card mb-4">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="small fw-bold text-muted mb-1">Search Students</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 rounded-start-pill pl-3">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </span>
                <input type="text" id="studentSearch" class="form-control border-start-0 rounded-end-pill py-2"
                    placeholder="Name or admission number...">
            </div>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold text-muted mb-1">Department</label>
            <select id="deptFilter" class="form-select rounded-pill py-2">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d) { ?>
                    <option value="<?php echo htmlspecialchars($d['code']); ?>">
                        <?php echo htmlspecialchars($d['code']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold text-muted mb-1">Level</label>
            <select id="levelFilter" class="form-select rounded-pill py-2">
                <option value="">All Levels</option>
                <option value="100L">100L</option>
                <option value="200L">200L</option>
                <option value="300L">300L</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button id="resetFilters"
                class="btn btn-light w-100 rounded-pill py-2 border fw-bold text-muted">Reset</button>
        </div>
    </div>
</div>

<!-- Student Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="studentTable">
            <thead>
                <tr class="text-muted">
                    <th style="width: 40%;">Student Info</th>
                    <th style="width: 25%;">Dept & Level</th>
                    <th style="width: 20%;">Password</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s) { ?>
                    <tr class="student-row" data-search="<?php echo strtolower($s['fullname'].' '.$s['username']); ?>"
                        data-dept="<?php echo htmlspecialchars($s['dept_code']); ?>"
                        data-level="<?php echo htmlspecialchars($s['level']); ?>">
                        <td>
                            <div class="fw-bold text-dark">
                                <?php echo htmlspecialchars($s['fullname']); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars($s['username']); ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border me-1">
                                <?php echo htmlspecialchars($s['dept_code']); ?>
                            </span>
                            <span class="badge bg-light text-dark border">
                                <?php echo htmlspecialchars($s['level']); ?>
                            </span>
                        </td>
                        <td>
                            <code class="text-primary fw-bold">******</code>
                        </td>
                    </tr>
                <?php } ?>
                <tr id="noResults" style="display: none;">
                    <td colspan="4" class="text-center py-5">
                        <div class="text-muted opacity-50 mb-2">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"
                                viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h5>No students found</h5>
                        <p class="small">Try adjusting your filters or search terms.</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4 border-top pt-3 px-2">
        <div class="small text-muted" id="paginationInfo">Showing 0 to 0 of 0 entries</div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0" id="pagination-controls"></ul>
        </nav>
    </div>
</div>


</div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0"
            style="border-radius: 24px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Admission Number</label>
                        <input type="text" id="edit_username" class="form-control rounded-pill border-0 bg-light py-2"
                            >
                        <small class="text-muted ps-2">Username cannot be changed.</small>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Full Name</label>
                        <input type="text" name="fullname" id="edit_fullname"
                            class="form-control rounded-pill border-0 shadow-sm py-2" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Password</label>
                        <input type="text" name="password" id="edit_password"
                            class="form-control rounded-pill border-0 shadow-sm py-2" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold text-muted mb-1">Department</label>
                            <select name="dept" id="edit_dept" class="form-select rounded-pill border-0 shadow-sm py-2"
                                required>
                                <?php foreach ($departments as $d) { ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['code']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold text-muted mb-1">Level</label>
                            <select name="level" id="edit_level"
                                class="form-select rounded-pill border-0 shadow-sm py-2" required>
                                <option value="100L">100L</option>
                                <option value="200L">200L</option>
                                <option value="300L">300L</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_student" class="btn btn-primary rounded-pill px-4 fw-bold">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0"
            style="border-radius: 24px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Register Individual Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Admission Number (Username)</label>
                        <input type="text" name="username" class="form-control rounded-pill border-0 shadow-sm py-2"
                            required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Full Name</label>
                        <input type="text" name="fullname" class="form-control rounded-pill border-0 shadow-sm py-2"
                            required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Password</label>
                        <input type="text" name="password" class="form-control rounded-pill border-0 shadow-sm py-2"
                            required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold text-muted mb-1">Department</label>
                            <select name="dept" class="form-select rounded-pill border-0 shadow-sm py-2" required>
                                <?php foreach ($departments as $d) { ?>
                                    <option value="<?php echo $d['id']; ?>">
                                        <?php echo htmlspecialchars($d['code']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold text-muted mb-1">Level</label>
                            <select name="level" class="form-select rounded-pill border-0 shadow-sm py-2" required>
                                <option value="100L">100L</option>
                                <option value="200L">200L</option>
                                <option value="300L">300L</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_student" class="btn btn-primary rounded-pill px-4 fw-bold">Register
                        Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_id" id="deleteId">
</form>

</main>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {
        <?php if (isset($student_msg)) { ?>
            Swal.fire({
                icon: '<?php echo $student_msg['success'] ? 'success' : 'error'; ?>',
                title: '<?php echo $student_msg['success'] ? 'Success' : 'Error'; ?>',
                text: '<?php echo $student_msg['message']; ?>'
            });
        <?php } ?>

        let currentPage = 1;
        const pageSize = 15;
        let visibleRows = [];

        function updatePagination() {
            const totalItems = visibleRows.length;
            const totalPages = Math.ceil(totalItems / pageSize);
            if (currentPage > totalPages) currentPage = totalPages || 1;
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            $('.student-row').hide();
            visibleRows.slice(start, end).forEach(row => $(row).show());
            const displayStart = totalItems === 0 ? 0 : start + 1;
            const displayEnd = Math.min(end, totalItems);
            $('#paginationInfo').text(`Showing ${displayStart} to ${displayEnd} of ${totalItems} entries`);

            let paginationHtml = `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link rounded-pill px-3 me-1" href="#" data-page="${currentPage - 1}">Prev</a></li>`;

            if (totalPages <= 7) {
                for (let i = 1; i <= totalPages; i++) {
                    paginationHtml += `<li class="page-item ${currentPage === i ? 'active' : ''}"><a class="page-link rounded-pill px-3 me-1" href="#" data-page="${i}">${i}</a></li>`;
                }
            } else {
                // Always show page 1
                paginationHtml += `<li class="page-item ${currentPage === 1 ? 'active' : ''}"><a class="page-link rounded-pill px-3 me-1" href="#" data-page="1">1</a></li>`;

                let startPage = Math.max(2, currentPage - 1);
                let endPage = Math.min(totalPages - 1, currentPage + 1);

                if (currentPage <= 3) endPage = 4;
                if (currentPage >= totalPages - 2) startPage = totalPages - 3;

                if (startPage > 2) paginationHtml += `<li class="page-item disabled"><span class="page-link px-2">...</span></li>`;

                for (let i = startPage; i <= endPage; i++) {
                    paginationHtml += `<li class="page-item ${currentPage === i ? 'active' : ''}"><a class="page-link rounded-pill px-3 me-1" href="#" data-page="${i}">${i}</a></li>`;
                }

                if (endPage < totalPages - 1) paginationHtml += `<li class="page-item disabled"><span class="page-link px-2">...</span></li>`;

                // Always show last page
                paginationHtml += `<li class="page-item ${currentPage === totalPages ? 'active' : ''}"><a class="page-link rounded-pill px-3 me-1" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
            }

            paginationHtml += `<li class="page-item ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}"><a class="page-link rounded-pill px-3" href="#" data-page="${currentPage + 1}">Next</a></li>`;
            $('#pagination-controls').html(paginationHtml);
        }

        function filterStudents() {
            const searchTerm = $('#studentSearch').val().toLowerCase();
            const deptFilter = $('#deptFilter').val();
            const levelFilter = $('#levelFilter').val();

            visibleRows = [];
            $('.student-row').each(function () {
                const row = $(this);
                const matchesSearch = row.data('search').includes(searchTerm);
                const matchesDept = deptFilter === "" || row.data('dept') === deptFilter;
                const matchesLevel = levelFilter === "" || row.data('level') === levelFilter;

                if (matchesSearch && matchesDept && matchesLevel) {
                    visibleRows.push(this);
                }
            });

            if (visibleRows.length === 0) { $('#noResults').show(); $('.student-row').hide(); } else { $('#noResults').hide(); }
            currentPage = 1;
            updatePagination();
        }

        $(document).on('click', '.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page !== currentPage) { currentPage = page; updatePagination(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
        });

        $('#studentSearch').on('input', filterStudents);
        $('#deptFilter, #levelFilter').on('change', filterStudents);
        $('#resetFilters').on('click', function () {
            $('#studentSearch').val('');
            $('#deptFilter, #levelFilter').val('');
            filterStudents();
        });

        $('.delete-btn').on('click', function () {
            const id = $(this).data('id');
            const name = $(this).data('name');
            Swal.fire({
                title: 'Delete student?',
                text: `Are you sure you want to delete ${name}? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#deleteId').val(id);
                    $('#deleteForm').submit();
                }
            });
        });

        // Edit Student Logic
        $('.edit-btn').on('click', function () {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const user = $(this).data('user');
            const pass = $(this).data('pass');
            const dept = $(this).data('dept');
            const level = $(this).data('level');

            $('#edit_user_id').val(id);
            $('#edit_fullname').val(name);
            $('#edit_username').val(user);
            $('#edit_password').val(pass);
            $('#edit_dept').val(dept);
            $('#edit_level').val(level);

            $('#editStudentModal').modal('show');
        });

        filterStudents();
    });
</script>
<style>
    .pagination .page-link {
        border: none;
        color: var(--text-muted);
        font-weight: 600;
        background: transparent;
        transition: all 0.2s ease;
    }

    .pagination .page-item.active .page-link {
        background: var(--primary-color);
        color: white;
        box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
    }

    .pagination .page-item.disabled .page-link {
        background: transparent;
        opacity: 0.5;
    }

    .pagination .page-link:hover:not(.disabled) {
        background: rgba(76, 175, 80, 0.1);
        color: var(--primary-color);
    }

    /* Layout Optimization */
    .admin-main {
        padding: 25px !important;
    }

    .glass-card {
        padding: 18px !important;
    }

    #studentTable th,
    #studentTable td {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    .badge {
        font-weight: 500;
        font-size: 0.75rem;
    }

    code {
        font-size: 0.85rem;
    }
</style>
</body>

</html>
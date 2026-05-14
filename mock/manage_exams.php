<?php
require 'admin_auth.php';

// Handle Status Toggle (MUST HAPPEN BEFORE ANY HTML OUTPUT)
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare('UPDATE exams SET status = 1 - status WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: manage_exams.php?success=status_updated');
    exit();
}

// Handle Delete (MUST HAPPEN BEFORE ANY HTML OUTPUT)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare('DELETE FROM exams WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: manage_exams.php?success=exam_deleted');
    exit();
}

$exams = $pdo->query('SELECT * FROM exams ORDER BY status ASC')->fetchAll();

// Mapping for Departments
$dept_map = [
    '1' => 'CHEW',
    '2' => 'HIM',
    '3' => 'MLT',
    '4' => 'PT',
];

// Now include the layout (HTML starts here)
require 'admin_layout.php';
?>

        <div class="page-header">
            <div class="page-title">
                <h2>Manage Examinations</h2>
                <p>View, edit, and control the visibility of your exams.</p>
            </div>
            <div class="page-actions">
                <a href="upload.php" class="btn btn-primary fw-bold px-4 py-2 rounded-pill">
                    + New Examination
                </a>
            </div>
        </div>

        <div class="glass-card mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="small fw-bold text-muted mb-1">Search Examinations</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill pl-3">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" id="examSearch" class="form-control border-start-0 rounded-end-pill py-2" placeholder="Search by code or title...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1">Department</label>
                    <select id="deptFilter" class="form-select rounded-pill py-2">
                        <option value="">All Departments</option>
                        <?php foreach ($dept_map as $id => $name) { ?>
                            <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold text-muted mb-1">Level</label>
                    <select id="levelFilter" class="form-select rounded-pill py-2">
                        <option value="">All Levels</option>
                        <option value="100L">100L</option>
                        <option value="200L">200L</option>
                        <option value="300L">300L</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button id="resetFilters" class="btn btn-light w-100 rounded-pill py-2 border fw-bold text-muted">Reset</button>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="examsTable">
                    <thead>
                        <tr class="text-muted">
                            <th>Exam Details</th>
                            <th>Configuration</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam) { ?>
                        <tr class="exam-row" 
                            data-dept="<?php echo $exam['dept']; ?>" 
                            data-level="<?php echo $exam['level']; ?>"
                            data-search="<?php echo strtolower($exam['code'].' '.$exam['title']); ?>">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-light p-2 rounded-3 border fw-bold text-primary">
                                        <?php echo htmlspecialchars($exam['code']); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($exam['title']); ?></div>
                                        <div class="small text-muted">Level: <?php echo htmlspecialchars($exam['level']); ?> | <?php echo isset($dept_map[$exam['dept']]) ? $dept_map[$exam['dept']] : 'N/A'; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-600"><?php echo $exam['time_allowed']; ?> Mins</div>
                                <div class="small text-muted"><?php echo $exam['number_of_questions']; ?> Questions</div>
                            </td>
                            <td>
                                <div class="small"><?php echo date('M d, Y', strtotime($exam['date'])); ?></div>
                            </td>
                            <td>
                                <div class="form-check form-switch p-0 d-flex align-items-center gap-2">
                                    <input class="form-check-input ms-0 status-toggle" type="checkbox" 
                                           <?php echo $exam['status'] == 0 ? 'checked' : ''; ?> 
                                           data-id="<?php echo $exam['id']; ?>">
                                    <span class="small fw-bold <?php echo $exam['status'] == 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $exam['status'] == 0 ? 'Active' : 'Hidden'; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-pill px-3 fw-bold border" type="button" data-bs-toggle="dropdown">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2">
                                        <li><a class="dropdown-item py-2 rounded-3" href="upload.php?edit_id=<?php echo $exam['id']; ?>">Edit details</a></li>
                                        <li><a class="dropdown-item py-2 rounded-3" href="download_exam.php?id=<?php echo $exam['id']; ?>">Download Questions</a></li>
                                        <li><hr class="dropdown-divider opacity-50"></li>
                                        <li><a class="dropdown-item py-2 rounded-3 text-danger delete-exam" href="#" data-id="<?php echo $exam['id']; ?>">Delete Exam</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr id="noResults" style="display: none;">
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="mb-3 opacity-25"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    <h5>No examinations found</h5>
                                    <p class="small">Try adjusting your search or filters to find what you're looking for.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 border-top pt-3 px-2">
                <div class="small text-muted" id="paginationInfo">
                    Showing 0 to 0 of 0 entries
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0" id="pagination-controls">
                        <!-- Pagination buttons will be injected here -->
                    </ul>
                </nav>
            </div>
        </div>
    </main>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let currentPage = 1;
    const pageSize = 10;
    let visibleRows = [];

    function updatePagination() {
        const totalItems = visibleRows.length;
        const totalPages = Math.ceil(totalItems / pageSize);
        
        if (currentPage > totalPages) currentPage = totalPages || 1;
        
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;
        
        $('.exam-row').hide();
        
        visibleRows.slice(start, end).forEach(row => $(row).show());
        
        // Update Info
        const displayStart = totalItems === 0 ? 0 : start + 1;
        const displayEnd = Math.min(end, totalItems);
        $('#paginationInfo').text(`Showing ${displayStart} to ${displayEnd} of ${totalItems} entries`);
        
        // Render Controls
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

    // Live Search and Filtering Logic
    function filterExams() {
        const searchTerm = $('#examSearch').val().toLowerCase();
        const deptFilter = $('#deptFilter').val();
        const levelFilter = $('#levelFilter').val();
        
        visibleRows = [];
        
        $('.exam-row').each(function() {
            const row = $(this);
            const searchData = row.data('search');
            const rowDept = row.data('dept').toString();
            const rowLevel = row.data('level');
            
            const matchesSearch = searchData.includes(searchTerm);
            const matchesDept = deptFilter === "" || rowDept === deptFilter;
            const matchesLevel = levelFilter === "" || rowLevel === levelFilter;
            
            if (matchesSearch && matchesDept && matchesLevel) {
                visibleRows.push(this);
            }
        });
        
        if (visibleRows.length === 0) {
            $('#noResults').show();
            $('.exam-row').hide();
        } else {
            $('#noResults').hide();
        }
        
        currentPage = 1; // Reset to page 1 on filter
        updatePagination();
    }

    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage) {
            currentPage = page;
            updatePagination();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    $('#examSearch').on('input', filterExams);
    $('#deptFilter, #levelFilter').on('change', filterExams);
    
    $('#resetFilters').on('click', function() {
        $('#examSearch').val('');
        $('#deptFilter').val('');
        $('#levelFilter').val('');
        filterExams();
    });

    // Initial setup
    filterExams();

    // Status Toggle
    $('.status-toggle').on('change', function() {
        const id = $(this).data('id');
        window.location.href = `manage_exams.php?toggle_status=1&id=${id}`;
    });

    // Delete confirmation
    $('.delete-exam').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "This will permanently delete the exam and ALL associated questions!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e03131',
            cancelButtonColor: '#adb5bd',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `manage_exams.php?delete=1&id=${id}`;
            }
        });
    });

    // Success Alerts
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        const type = urlParams.get('success');
        let msg = '';
        if (type === 'status_updated') msg = 'Exam visibility updated successfully.';
        if (type === 'exam_deleted') msg = 'Examination has been removed.';
        
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: msg,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    }
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
</style>
</body>
</html>

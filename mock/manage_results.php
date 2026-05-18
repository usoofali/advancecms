<?php
require 'admin_auth.php';

// Fetch all exams for filter dropdown
$exams = $pdo->query('SELECT id, code, title FROM exams ORDER BY code ASC')->fetchAll();

// Fetch results with user details
// Using the successful query from test_query.php
try {
    $results = $pdo->query('
        SELECT 
            s.*, 
            u.fullname, 
            e.code as exam_code, 
            e.title as exam_title,
            e.level as exam_level
        FROM exam_session s
        JOIN users u ON s.user_id = u.user_id
        JOIN exams e ON s.exam = e.id
        WHERE s.submit_status = 1
        ORDER BY s.started_at DESC
    ')->fetchAll();
} catch (PDOException $e) {
    exit('Database Error: '.$e->getMessage());
}

require 'admin_layout.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div class="page-title">
        <h2>Examination Results</h2>
        <p>Review student performance records across all departments.</p>
    </div>
</div>

<!-- Filters Section -->
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
                <input type="text" id="resultSearch" class="form-control border-start-0 rounded-end-pill py-2"
                    placeholder="Name or registration number...">
            </div>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold text-muted mb-1">Filter by Exam</label>
            <select id="examFilter" class="form-select rounded-pill py-2">
                <option value="">All Examinations</option>
                <?php foreach ($exams as $e) { ?>
                    <option value="<?php echo htmlspecialchars($e['code']); ?>">
                        <?php echo htmlspecialchars($e['code'].' - '.$e['title']); ?>
                    </option>
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
            <button id="resetFilters"
                class="btn btn-light w-100 rounded-pill py-2 border fw-bold text-muted">Clear</button>
        </div>
    </div>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="resultsTable">
            <thead>
                <tr class="text-muted">
                    <th>Student Details</th>
                    <th>Examination</th>
                    <th class="text-center">Attempted</th>
                    <th class="text-center">Exam Score</th>
                    <th class="text-center">Sync Status</th>
                    <th class="text-end">Percentage</th>
                    <th class="text-end">Audit Trail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r) { ?>
                    <tr class="result-row" data-search="<?php echo strtolower($r['fullname'].' '.$r['username']); ?>"
                        data-exam="<?php echo htmlspecialchars($r['exam_code']); ?>"
                        data-level="<?php echo htmlspecialchars($r['exam_level']); ?>">
                        <td>
                            <div class="fw-bold text-dark">
                                <?php echo htmlspecialchars($r['fullname']); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars($r['username']); ?>
                            </div>
                        </td>
                        <td>
                            <div class="small fw-600 text-primary">
                                <?php echo htmlspecialchars($r['exam_code']); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars($r['exam_title']); ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <?php echo $r['attempted']; ?> /
                                <?php echo $r['total_questions']; ?>
                            </span>
                        </td>
                        <td class="text-center fw-600">
                            <?php echo $r['total_score']; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($r['is_synced']) { ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Synced</span>
                            <?php } else { ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pending</span>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <div class="fw-bold text-success">
                                <?php echo number_format($r['percent_score'], 1); ?>%
                            </div>
                            <div class="progress mt-1" style="height: 4px; border-radius: 2px;">
                                <div class="progress-bar bg-success" role="progressbar"
                                    style="width: <?php echo $r['percent_score']; ?>%"></div>
                            </div>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light border rounded-pill px-3 audit-btn" data-session="<?php echo $r['session_id']; ?>" data-student="<?php echo htmlspecialchars($r['fullname']); ?>">
                                Audit
                            </button>
                        </td>
                    </tr>
                        
                <?php } ?>
                <tr id="noResults" style="display: none;">
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"
                                viewBox="0 0 24 24" class="mb-3 opacity-25">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <h5>No results found</h5>
                            <p class="small">Try adjusting your search or filters.</p>
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
            <ul class="pagination pagination-sm mb-0" id="pagination-controls"></ul>
        </nav>
    </div>
</div>

</main>
</div>

<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {
        let currentPage = 1;
        const pageSize = 15;
        let visibleRows = [];

        function updatePagination() {
            const totalItems = visibleRows.length;
            const totalPages = Math.ceil(totalItems / pageSize);
            if (currentPage > totalPages) currentPage = totalPages || 1;
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            $('.result-row').hide();
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

        function filterResults() {
            const searchTerm = $('#resultSearch').val().toLowerCase();
            const examFilter = $('#examFilter').val();
            const levelFilter = $('#levelFilter').val();

            visibleRows = [];
            $('.result-row').each(function () {
                const row = $(this);
                const matchesSearch = row.data('search').includes(searchTerm);
                const matchesExam = examFilter === "" || row.data('exam') === examFilter;
                const matchesLevel = !levelFilter || levelFilter === "" || row.data('level') == levelFilter;

                if (matchesSearch && matchesExam && matchesLevel) {
                    visibleRows.push(this);
                }
            });

            if (visibleRows.length === 0) { $('#noResults').show(); $('.result-row').hide(); } else { $('#noResults').hide(); }
            currentPage = 1;
            updatePagination();
        }

        $(document).on('click', '.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page !== currentPage) { currentPage = page; updatePagination(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
        });

        $('#resultSearch').on('input', filterResults);
        $('#examFilter, #levelFilter').on('change', filterResults);
        $('#resetFilters').on('click', function () {
            $('#resultSearch').val('');
            $('#examFilter, #levelFilter').val('');
            filterResults();
        });

        filterResults();
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

    .fw-600 {
        font-weight: 600;
    }
</style>
</body>

</html>
<!-- Audit Modal -->
<div class="modal fade" id="auditModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 bg-light" style="border-radius: 20px 20px 0 0;">
                <h5 class="modal-title fw-bold">Response Audit: <span id="auditStudentName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="auditContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading responses...</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.audit-btn').on('click', function() {
        const sessionId = $(this).data('session');
        const studentName = $(this).data('student');
        
        $('#auditStudentName').text(studentName);
        $('#auditContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading responses...</p></div>');
        $('#auditModal').modal('show');
        
        $.get('get_responses.php', { session_id: sessionId }, function(html) {
            $('#auditContent').html(html);
        });
    });
});
</script>
</body>
</html>

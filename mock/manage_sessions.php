<?php
require 'admin_auth.php';

// Handle Force Submit (AJAX)
if (isset($_POST['force_submit'])) {
    $session_id = $_POST['session_id'];

    // 1. Get session info
    $stmt = $pdo->prepare('SELECT user_id, exam FROM exam_session WHERE session_id = ?');
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if ($session) {
        $user_id = $session['user_id'];
        $exam_id = $session['exam'];

        // 2. Calculate score (same logic as submit_exam.php)
        $query = 'SELECT user_answer, answer FROM answers WHERE user_id = :user_id and exam = :exam';
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $user_id, 'exam' => $exam_id]);

        $score_counter = 0;
        $total_questions = 0;
        $attempted = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['user_answer'] === $row['answer']) {
                $score_counter++;
            }
            if ($row['user_answer'] != '0' && $row['user_answer'] != '') {
                $attempted++;
            }
            $total_questions++;
        }

        $percent_score = $total_questions > 0 ? ($score_counter / $total_questions) * 100 : 0;

        // 3. Update session to submitted status
        $update_query = 'UPDATE exam_session SET submit_status = 1, total_score = ?, percent_score = ?, total_questions = ?, attempted = ? WHERE session_id = ?';
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$score_counter, $percent_score, $total_questions, $attempted, $session_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Session not found.']);
    }
    exit();
}

// Handle Time Extension (AJAX)
if (isset($_POST['extend_time'])) {
    $session_id = $_POST['session_id'];
    $minutes = (int) $_POST['minutes'];

    $stmt = $pdo->prepare('UPDATE exam_session SET stop_at = DATE_ADD(stop_at, INTERVAL ? MINUTE) WHERE session_id = ?');
    $stmt->execute([$minutes, $session_id]);
    echo json_encode(['success' => true]);
    exit();
}

// Handle Reset Attempt (AJAX)
if (isset($_POST['reset_session'])) {
    $session_id = $_POST['session_id'];

    $stmt = $pdo->prepare('SELECT user_id, exam FROM exam_session WHERE session_id = ?');
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if ($session) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM answers WHERE user_id = ? AND exam = ?');
            $stmt->execute([$session['user_id'], $session['exam']]);
            $stmt = $pdo->prepare('DELETE FROM exam_session WHERE session_id = ?');
            $stmt->execute([$session_id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Session not found.']);
    }
    exit();
}

// Fetch Data for Display
$sessions = $pdo->query('
    SELECT s.*, e.code, e.title 
    FROM exam_session s 
    JOIN exams e ON s.exam = e.id 
    ORDER BY s.started_at DESC
')->fetchAll();

// Include Layout
require 'admin_layout.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2>Active Sessions</h2>
        <p>Monitor student progress and manage examination timers in real-time.</p>
    </div>
</div>

<div class="glass-card mb-4">
    <div class="row g-3">
        <div class="col-md-7">
            <label class="small fw-bold text-muted mb-1">Search Students</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 rounded-start-pill pl-3">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </span>
                <input type="text" id="sessionSearch" class="form-control border-start-0 rounded-end-pill py-2"
                    placeholder="Search by name or ID...">
            </div>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold text-muted mb-1">Status Filter</label>
            <select id="statusFilter" class="form-select rounded-pill py-2">
                <option value="">All Statuses</option>
                <option value="live">Live</option>
                <option value="submitted">Submitted</option>
                <option value="abandoned">Abandoned</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button id="resetFilters"
                class="btn btn-light w-100 rounded-pill py-2 border fw-bold text-muted">Reset</button>
        </div>
    </div>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="sessionsTable">
            <thead>
                <tr class="text-muted">
                    <th>Student</th>
                    <th>Examination</th>
                    <th>Timing</th>
                    <th>Status</th>
                    <th class="text-end">Control</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s) {
                    $is_expired = time() > strtotime($s['stop_at']);
                    $status_class = 'live';
                    if ($s['submit_status'] == 1) {
                        $status_class = 'submitted';
                    } elseif ($is_expired) {
                        $status_class = 'abandoned';
                    }
                    ?>
                    <tr class="session-row" data-status="<?php echo $status_class; ?>"
                        data-search="<?php echo strtolower($s['username'].' '.$s['user_id']); ?>">
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['username']); ?></div>
                            <div class="small text-muted">ID: <?php echo htmlspecialchars($s['user_id']); ?></div>
                        </td>
                        <td>
                            <div class="small fw-600 text-primary"><?php echo htmlspecialchars($s['code']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($s['title']); ?></div>
                        </td>
                        <td>
                            <div class="small fw-600">Start: <?php echo date('H:i:s', strtotime($s['started_at'])); ?></div>
                            <div class="small text-danger">End: <?php echo date('H:i:s', strtotime($s['stop_at'])); ?></div>
                        </td>
                        <td>
                            <?php if ($status_class == 'submitted') { ?>
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge bg-success opacity-75">Submitted</span>
                                    <?php if ($s['is_synced']) { ?>
                                        <span class="badge bg-info x-small" style="font-size: 0.65rem;">Synced to CMS</span>
                                    <?php } else { ?>
                                        <span class="badge bg-warning text-dark x-small" style="font-size: 0.65rem;">Pending Sync</span>
                                    <?php } ?>
                                </div>
                            <?php } elseif ($status_class == 'abandoned') { ?>
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge bg-danger opacity-75">Abandoned</span>
                                    <span class="x-small text-muted" style="font-size: 0.7rem;">Expired w/o submit</span>
                                </div>
                            <?php } else { ?>
                                <span class="badge bg-primary rounded-pill px-3">Live</span>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <?php if ($status_class == 'abandoned') { ?>
                                    <button class="btn btn-warning btn-sm rounded-pill px-3 force-submit-btn"
                                        data-id="<?php echo $s['session_id']; ?>"
                                        data-user="<?php echo htmlspecialchars($s['username']); ?>">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24" class="me-1">
                                            <path d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Force Submit
                                    </button>
                                <?php } ?>

                                <?php if ($status_class == 'live') { ?>
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3 extend-btn"
                                        data-id="<?php echo $s['session_id']; ?>"
                                        data-user="<?php echo htmlspecialchars($s['username']); ?>">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24" class="me-1">
                                            <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
                                        </svg>
                                        + Time
                                    </button>
                                <?php } ?>

                                <button class="btn btn-outline-danger btn-sm rounded-pill px-3 reset-btn"
                                    data-id="<?php echo $s['session_id']; ?>"
                                    data-user="<?php echo htmlspecialchars($s['username']); ?>">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24" class="me-1">
                                        <path
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                    Reset
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                <tr id="noResults" style="display: none;">
                    <td colspan="5" class="text-center py-5">
                        <div class="text-muted">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"
                                viewBox="0 0 24 24" class="mb-3 opacity-25">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <h5>No sessions found</h5>
                            <p class="small">Try adjusting your search or status filter.</p>
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
        const pageSize = 10;
        let visibleRows = [];

        function updatePagination() {
            const totalItems = visibleRows.length;
            const totalPages = Math.ceil(totalItems / pageSize);
            if (currentPage > totalPages) currentPage = totalPages || 1;
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            $('.session-row').hide();
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

        function filterSessions() {
            const searchTerm = $('#sessionSearch').val().toLowerCase();
            const statusFilter = $('#statusFilter').val();
            visibleRows = [];
            $('.session-row').each(function () {
                const row = $(this);
                const searchData = row.data('search');
                const rowStatus = row.data('status');
                const matchesSearch = searchData.includes(searchTerm);
                const matchesStatus = statusFilter === "" || rowStatus === statusFilter;
                if (matchesSearch && matchesStatus) visibleRows.push(this);
            });
            if (visibleRows.length === 0) { $('#noResults').show(); $('.session-row').hide(); } else { $('#noResults').hide(); }
            currentPage = 1;
            updatePagination();
        }

        $(document).on('click', '.page-link', function (e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page !== currentPage) { currentPage = page; updatePagination(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
        });

        $('#sessionSearch').on('input', filterSessions);
        $('#statusFilter').on('change', filterSessions);
        $('#resetFilters').on('click', function () { $('#sessionSearch').val(''); $('#statusFilter').val(''); filterSessions(); });

        // Force Submit
        $(document).on('click', '.force-submit-btn', function () {
            const id = $(this).data('id');
            const user = $(this).data('user');
            Swal.fire({
                title: 'Force Submit?',
                text: `This will calculate the current score for ${user} and mark the exam as submitted.`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#4CAF50',
                confirmButtonText: 'Yes, Force Submit'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.post('manage_sessions.php', { force_submit: 1, session_id: id }, function (res) {
                        const data = JSON.parse(res);
                        if (data.success) {
                            Swal.fire('Submitted!', 'Session has been finalized.', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.error, 'error');
                        }
                    });
                }
            });
        });

        // Extend Time
        $(document).on('click', '.extend-btn', function () {
            const id = $(this).data('id');
            const user = $(this).data('user');
            Swal.fire({
                title: 'Extend Time',
                text: `How many minutes to add for ${user}?`,
                input: 'number',
                inputAttributes: { min: 1, step: 1 },
                inputValue: 10,
                showCancelButton: true,
                confirmButtonColor: '#4CAF50',
                confirmButtonText: 'Add Time'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('manage_sessions.php', { extend_time: 1, session_id: id, minutes: result.value }, function (res) {
                        const data = JSON.parse(res);
                        if (data.success) {
                            Swal.fire('Updated!', 'Time has been extended.', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.error || 'Failed to update time.', 'error');
                        }
                    });
                }
            });
        });

        // Reset Attempt
        $(document).on('click', '.reset-btn', function () {
            const id = $(this).data('id');
            const user = $(this).data('user');
            Swal.fire({
                title: 'Reset Attempt?',
                text: `This will clear all progress for ${user}. This cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e03131',
                confirmButtonText: 'Yes, Reset'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('manage_sessions.php', { reset_session: 1, session_id: id }, function (res) {
                        const data = JSON.parse(res);
                        if (data.success) {
                            Swal.fire('Reset!', 'The session has been cleared.', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.error, 'error');
                        }
                    });
                }
            });
        });

        filterSessions();
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
<?php
// mock/sync_manager.php
require 'admin_auth.php';
require 'sync_engine.php';

$message = '';
$messageType = 'info';

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'pull') {
        $result = pull_exams();
        $message = $result['success'] ? "Successfully pulled {$result['count']} exams from CMS." : $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
    } elseif ($_GET['action'] === 'push') {
        $result = push_results();
        $message = $result['success'] ? "Successfully pushed results for {$result['count']} exams to CMS." : $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_config'])) {
    $new_url = $_POST['cms_url'] ?? 'http://localhost:8000';
    $new_token = $_POST['api_token'] ?? '';
    $new_inst = (int) ($_POST['institution_id'] ?? 1);

    $config_content = "<?php\n\n// mock/sync_config.php\n\nreturn [\n";
    $config_content .= "    'cms_url' => '".addslashes($new_url)."',\n";
    $config_content .= "    'api_token' => '".addslashes($new_token)."',\n";
    $config_content .= "    'institution_id' => ".$new_inst.",\n";
    $config_content .= "];\n";

    if (file_put_contents('sync_config.php', $config_content)) {
        $message = 'Configuration updated successfully.';
        $messageType = 'success';
    } else {
        $message = 'Failed to update configuration. Check file permissions.';
        $messageType = 'danger';
    }
}

// Fetch Sync Stats
$cms_exams = $pdo->query('SELECT COUNT(*) FROM exams WHERE cms_uuid IS NOT NULL')->fetchColumn();
$local_only = $pdo->query('SELECT COUNT(*) FROM exams WHERE cms_uuid IS NULL')->fetchColumn();
$pending_results = $pdo->query('SELECT COUNT(*) FROM exam_session s JOIN exams e ON s.exam = e.id WHERE e.cms_uuid IS NOT NULL AND s.is_synced = 0 AND s.submit_status = 1')->fetchColumn();

// Fetch Sync History
$sync_history = $pdo->query('SELECT * FROM sync_logs ORDER BY created_at DESC LIMIT 10')->fetchAll();

require 'admin_layout.php';
?>

<div class="page-header mb-4">
    <div class="page-title">
        <h2>CMS Sync Manager</h2>
        <p>Orchestrate data flow between the CMS and this Lab Server.</p>
    </div>
</div>

<?php if ($message) { ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4 border-0 shadow-sm rounded-4" role="alert">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-<?php echo $messageType; ?> text-white p-2 rounded-circle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <span class="fw-bold"><?php echo $message; ?></span>
                <?php if (isset($result['errors']) && ! empty($result['errors'])) { ?>
                    <ul class="mb-0 mt-2 small">
                        <?php foreach ($result['errors'] as $err) {
                            echo "<li>$err</li>";
                        } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php } ?>

<div class="row g-4 mb-5">
    <div class="col-md-6">
        <div class="glass-card h-100 border-0 shadow-sm p-4 text-center">
            <div class="bg-primary text-white p-4 rounded-4 d-inline-block mb-4 shadow-lg">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
            </div>
            <h4 class="fw-800">Pull Exams</h4>
            <p class="text-muted mb-4 small">Download active exam packages, questions, and student PINs from the central CMS hub.</p>
            <div class="d-flex justify-content-between text-start mb-4 p-3 bg-light rounded-3">
                <span class="text-muted">CMS Linked Exams</span>
                <span class="fw-bold text-primary"><?php echo $cms_exams; ?></span>
            </div>
            <a href="?action=pull" id="pull-btn" class="btn btn-primary w-100 py-3 rounded-3 fw-bold">Manual Pull from CMS</a>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="glass-card h-100 border-0 shadow-sm p-4 text-center">
            <div class="bg-success text-white p-4 rounded-4 d-inline-block mb-4 shadow-lg">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
            </div>
            <h4 class="fw-800">Push Results</h4>
            <p class="text-muted mb-4 small">Upload completed exam sessions and student scores back to the CMS for administrative review.</p>
            <div class="d-flex justify-content-between text-start mb-4 p-3 bg-light rounded-3">
                <span class="text-muted">Unsynced Sessions</span>
                <span class="fw-bold text-success"><?php echo $pending_results; ?></span>
            </div>
            <a href="?action=push" id="push-btn" class="btn btn-success w-100 py-3 rounded-3 fw-bold">Manual Push to CMS</a>
            <?php if ($pending_results > 0) { ?>
                <div class="mt-2 small text-muted">Includes failed attempts ready for retry.</div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass-card p-4">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
                Sync History Log
            </h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="x-small text-muted text-uppercase fw-bold">
                            <th>Date & Time</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sync_history as $log) { ?>
                        <tr>
                            <td class="small"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo strtoupper($log['type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $log['status'] === 'success' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars($log['message']); ?></td>
                        </tr>
                        <?php } ?>
                        <?php if (empty($sync_history)) { ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted small">No sync events recorded yet.</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="glass-card p-4">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Sync Config
            </h5>
            <?php
            $config = require 'sync_config.php';
$hasToken = ! empty($config['api_token']);
?>
            <div class="table-responsive">
                <table class="table table-borderless align-middle small">
                    <tr class="border-bottom">
                        <td class="text-muted py-2">Endpoint</td>
                        <td class="fw-bold text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($config['cms_url']); ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="text-muted py-2">Auth</td>
                        <td>
                            <span class="badge rounded-pill <?php echo $hasToken ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $hasToken ? 'Active' : 'Missing'; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <button class="btn btn-outline-secondary w-100 btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#configModal">Edit Configuration</button>
        </div>
    </div>
</div>

<!-- Config Modal -->
<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Lab Connectivity Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="update_config" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">CMS Base URL</label>
                        <input type="url" name="cms_url" class="form-control form-control-lg bg-light border-0 rounded-3" value="<?php echo htmlspecialchars($config['cms_url']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Institution ID</label>
                        <input type="number" name="institution_id" class="form-control form-control-lg bg-light border-0 rounded-3" value="<?php echo htmlspecialchars($config['institution_id']); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">API Access Token (Sanctum)</label>
                        <textarea name="api_token" class="form-control form-control-lg bg-light border-0 rounded-3 font-mono" rows="3" placeholder="Paste token generated in CMS..."><?php echo htmlspecialchars($config['api_token']); ?></textarea>
                        <div class="form-text x-small text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i> Generate this token in the CMS <b>Sync Hub</b> for this specific server.
                        </div>
                    </div>

                    <div class="bg-blue-50 p-3 rounded-3 border border-blue-100 mb-2">
                        <div class="d-flex gap-2">
                            <svg width="18" height="18" fill="currentColor" class="text-primary shrink-0" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                            <p class="mb-0 x-small text-primary-emphasis">Ensure the Lab Server has outbound internet access to reach the CMS URL.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 p-4">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Discard</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('pull-btn').addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Pulling Data...',
            text: 'Communicating with the CMS to pull exams. Please wait.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        window.location.href = this.href;
    });

    document.getElementById('push-btn').addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Pushing Results...',
            text: 'Sending completed exam results to the CMS. Please wait.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        window.location.href = this.href;
    });
</script>

</main>
</div>
<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

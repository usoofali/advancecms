<?php

// mock/sync_engine.php

require_once 'db.php';
$config = require 'sync_config.php';

function cms_api_call($endpoint, $method = 'GET', $data = null)
{
    $config = require 'sync_config.php';
    $url = rtrim($config['cms_url'], '/').'/api/'.ltrim($endpoint, '/');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$config['api_token'],
        'Accept: application/json',
        'Content-Type: application/json',
    ]);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
    ];
}

function log_sync($type, $status, $message, $details = null)
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO sync_logs (type, status, message, details) VALUES (?, ?, ?, ?)');
    $stmt->execute([$type, $status, $message, $details ? json_encode($details) : null]);
}

function pull_exams()
{
    global $pdo;
    $response = cms_api_call('v1/cbt/exams');

    if ($response['code'] !== 200) {
        $msg = 'Failed to reach CMS: '.($response['body']['message'] ?? 'Unknown Error');
        log_sync('pull', 'fail', $msg);

        return ['success' => false, 'message' => $msg];
    }

    $exams = $response['body'];
    $count = 0;
    $exam_list = [];

    foreach ($exams as $exam) {
        // Download Package
        $pkgResponse = cms_api_call('v1/cbt/package/'.$exam['uuid']);
        if ($pkgResponse['code'] === 200) {
            $tempZip = 'temp_exam.zip';
            file_put_contents($tempZip, $pkgResponse['raw']);

            $zip = new ZipArchive;
            if ($zip->open($tempZip) === true) {
                $manifest = json_decode($zip->getFromName('manifest.json'), true);
                process_manifest($manifest, $pdo);
                $zip->close();
                unlink($tempZip);
                $count++;
                $exam_list[] = $exam['title'];
            }
        }
    }

    log_sync('pull', 'success', "Successfully pulled $count exam packages.", ['exams' => $exam_list]);

    return ['success' => true, 'count' => $count];
}

function process_manifest($data, $pdo)
{
    // 1. Sync Exam Header
    $exam = $data['exam'];
    $stmt = $pdo->prepare("INSERT INTO exams (cms_uuid, code, title, time_allowed, number_of_questions, status, open, session, semester, level, dept, description, date) 
                          VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, 'Sync from CMS', ?) 
                          ON DUPLICATE KEY UPDATE title=VALUES(title), code=VALUES(code), time_allowed=VALUES(time_allowed), date=VALUES(date), level=VALUES(level), dept=VALUES(dept), number_of_questions=VALUES(number_of_questions)");
    $stmt->execute([
        $exam['uuid'],
        substr($exam['course_code'] ?? $exam['uuid'], 0, 11),  // code is varchar(11)
        $exam['title'],
        $exam['duration'],
        $exam['total_questions'],
        $exam['session'] ?? '2025/2026',
        $exam['semester'] ?? 'First',
        $exam['level'] ?? '100',
        $exam['dept'] ?? 1,
        $exam['exam_date'] ?? null,
    ]);

    $localExamId = $pdo->query("SELECT id FROM exams WHERE cms_uuid='{$exam['uuid']}'")->fetchColumn();

    // 2. Sync Questions
    // Clear existing questions for this exam to prevent duplication
    $pdo->prepare('DELETE FROM question WHERE exam = ?')->execute([$localExamId]);
    foreach ($data['questions'] as $q) {
        $stmt = $pdo->prepare('INSERT INTO question (exam, question, option1, option2, option3, option4, answer) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)');

        $correct = 0;
        foreach ($q['options'] as $idx => $opt) {
            if ($opt['is_correct'] ?? false) {
                $correct = $idx + 1;
                break;
            }
        }

        $stmt->execute([
            $localExamId,
            $q['text'],
            $q['options'][0]['text'] ?? '',
            $q['options'][1]['text'] ?? '',
            $q['options'][2]['text'] ?? '',
            $q['options'][3]['text'] ?? '',
            $correct,
        ]);
    }

    // 3. Sync Students
    $examSemester = $exam['semester'] ?? 'First';
    $stmtCheck = $pdo->prepare('SELECT user_id FROM users WHERE username = ? AND semester = ?');
    $stmtInsert = $pdo->prepare("INSERT INTO users (username, fullname, password, dept, level, role, semester) VALUES (?, ?, ?, ?, ?, 'student', ?)");
    $stmtUpdate = $pdo->prepare('UPDATE users SET fullname = ?, password = ?, dept = ?, level = ? WHERE username = ? AND semester = ?');

    foreach ($data['students'] as $s) {
        $stmtCheck->execute([$s['matric_no'], $examSemester]);
        if ($stmtCheck->fetchColumn()) {
            $stmtUpdate->execute([$s['name'], $s['pin'], $exam['dept'] ?? 1, $exam['level'] ?? '100', $s['matric_no'], $examSemester]);
        } else {
            $stmtInsert->execute([$s['matric_no'], $s['name'], $s['pin'], $exam['dept'] ?? 1, $exam['level'] ?? '100', $examSemester]);
        }
    }
}

function push_results()
{
    global $pdo;

    // Find exams with completed (submitted) results
    $exams = $pdo->query('
        SELECT DISTINCT e.id, e.cms_uuid, e.title 
        FROM exam_session s 
        JOIN exams e ON s.exam = e.id 
        WHERE e.cms_uuid IS NOT NULL AND s.submit_status = 1 AND s.is_synced = 0
    ')->fetchAll();

    $pushed_count = 0;
    $errors = [];

    foreach ($exams as $exam) {
        $results_stmt = $pdo->prepare('SELECT * FROM exam_session WHERE exam = ? AND submit_status = 1 AND is_synced = 0');
        $results_stmt->execute([$exam['id']]);
        $rows = $results_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            continue;
        }

        $payload = [
            'exam_uuid' => $exam['cms_uuid'],
            'submission_token' => bin2hex(random_bytes(16)),
            'results' => [],
        ];

        $session_ids = [];
        foreach ($rows as $r) {
            // Build real per-question responses from the answers table
            $responses = [];
            $ans_stmt = $pdo->prepare('SELECT * FROM answers WHERE user_id = ? AND exam = ?');
            $ans_stmt->execute([$r['user_id'], $r['exam']]);
            $answers = $ans_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($answers as $a) {
                $correctIdx = (int) $a['answer'];
                $chosenIdx = (int) $a['user_answer'];

                $responses[] = [
                    'question_number' => $a['question_number'],
                    'question_text' => $a['question'],
                    'correct_option' => $correctIdx,
                    'correct_text' => $a['option'.$correctIdx] ?? 'N/A',
                    'chosen_option' => $chosenIdx,
                    'chosen_text' => $chosenIdx > 0 ? ($a['option'.$chosenIdx] ?? 'N/A') : 'No Answer',
                    'is_correct' => $chosenIdx === $correctIdx,
                ];
            }

            $payload['results'][] = [
                'matric_no' => $r['username'],
                'score_raw' => $r['total_score'],
                'score_percent' => $r['percent_score'],
                'attempt' => $r['attempt_number'] ?? 1,
                'attempt_type' => $r['attempt_type'] ?? 'normal',
                'responses' => $responses,
            ];
            $session_ids[] = $r['session_id'];
        }

        $response = cms_api_call('v1/cbt/results', 'POST', $payload);
        if ($response['code'] === 200) {
            // Mark as synced locally
            $placeholders = implode(',', array_fill(0, count($session_ids), '?'));
            $pdo->prepare("UPDATE exam_session SET is_synced = 1 WHERE session_id IN ($placeholders)")->execute($session_ids);

            $pushed_count += count($session_ids);
            log_sync('push', 'success', 'Pushed '.count($session_ids)." results for {$exam['title']}", ['exam_id' => $exam['id']]);
        } else {
            $msg = "Failed to push results for {$exam['title']}: ".($response['body']['message'] ?? 'Unknown Error');
            log_sync('push', 'fail', $msg, ['payload' => $payload]);
            $errors[] = $msg;
        }
    }

    return [
        'success' => empty($errors),
        'count' => $pushed_count,
        'errors' => $errors,
    ];
}

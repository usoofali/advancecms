<?php
require 'admin_auth.php';

if (! isset($_GET['session_id'])) {
    exit('Missing session ID');
}

$session_id = $_GET['session_id'];

// Get session info
$stmt = $pdo->prepare('SELECT user_id, exam FROM exam_session WHERE session_id = ?');
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (! $session) {
    exit('Session not found');
}

// Get answers and questions
$stmt = $pdo->prepare('
    SELECT a.*, q.answer as correct_answer 
    FROM answers a 
    LEFT JOIN question q ON a.question = q.question AND a.exam = q.exam
    WHERE a.user_id = ? AND a.exam = ?
');
// Wait, the answers table structure from mock.sql:
// answers (question_answer_id, question_number, question, option1, option2, option3, option4, answer, user_answer, user_id, exam)
// So we just need the answers table.

$stmt = $pdo->prepare('SELECT * FROM answers WHERE user_id = ? AND exam = ? ORDER BY question_number ASC');
$stmt->execute([$session['user_id'], $session['exam']]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="table-responsive">
    <table class="table table-sm small">
        <thead>
            <tr class="bg-light">
                <th>#</th>
                <th>Question</th>
                <th>Student's Choice</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($answers as $a) {
                $is_correct = ($a['user_answer'] == $a['answer']);
                $options = [
                    1 => $a['option1'],
                    2 => $a['option2'],
                    3 => $a['option3'],
                    4 => $a['option4'],
                ];
                $choice_text = $options[$a['user_answer']] ?? 'No Answer';
                $correct_text = $options[$a['answer']] ?? 'N/A';
                ?>
            <tr>
                <td><?php echo $a['question_number']; ?></td>
                <td>
                    <div class="fw-bold"><?php echo htmlspecialchars($a['question']); ?></div>
                    <div class="x-small text-muted">Correct: <?php echo htmlspecialchars($correct_text); ?></div>
                </td>
                <td class="<?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                    <?php echo htmlspecialchars($choice_text); ?>
                </td>
                <td>
                    <?php if ($is_correct) { ?>
                        <span class="text-success"><svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022z"/></svg></span>
                    <?php } else { ?>
                        <span class="text-danger"><svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg></span>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

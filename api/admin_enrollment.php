<?php
/* =========================================================
   POST api/admin_enrollment.php   body (JSON): {"id": 10451, "status": "Confirmed"}
   Admin changes the status of an enrollment with AJAX.
   - only administrators (403 for others, 401 for guests)
   - CSRF token in the X-CSRF-Token header
   - a Cancelled/Completed enrollment can be made active again
     only if the group still has a free seat and the student has
     no schedule conflict (the same business rules as enroll_user)
   Answer: {"success": true, "status": "Confirmed", "counts": {"Pending": 5, ...}}
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}
api_require_admin();
if (!csrf_is_valid()) {
    json_error('Invalid CSRF token. Reload the page.', 403);
}
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$status = (string)($input['status'] ?? '');
if (!in_array($status, ['Pending', 'Confirmed', 'Completed', 'Cancelled'], true)) {
    json_error('Invalid status.', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT e.id, e.user_id, e.group_id, e.status, g.capacity FROM enrollments e
                           JOIN course_groups g ON g.id = e.group_id WHERE e.id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) {
        $pdo->rollBack();
        json_error('Enrollment not found.', 404);
    }
    $wasActive = in_array($e['status'], ['Pending', 'Confirmed'], true);
    $becomesActive = in_array($status, ['Pending', 'Confirmed'], true);
    if (!$wasActive && $becomesActive) {
        // re-activation: check seats and schedule again
        if (seats_taken((int)$e['group_id']) >= (int)$e['capacity']) {
            $pdo->rollBack();
            json_error('The group is full: this enrollment cannot be activated.', 409);
        }
        $courseId = (int)db_value('SELECT course_id FROM course_groups WHERE id = ?', [$e['group_id']]);
        if (active_enrollment_in_course((int)$e['user_id'], $courseId)) {
            $pdo->rollBack();
            json_error('The student already has another active enrollment in this course.', 409);
        }
        $conflicts = find_schedule_conflicts((int)$e['user_id'], (int)$e['group_id']);
        if ($conflicts) {
            $pdo->rollBack();
            json_error(conflict_message($conflicts), 409);
        }
    }
    db_exec('UPDATE enrollments SET status = ?, updated_at = ? WHERE id = ?', [$status, date('Y-m-d H:i:s'), $id]);
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $ex;
}

$counts = [];
foreach (db_all('SELECT status, COUNT(*) AS cnt FROM enrollments GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['cnt'];
}
json_response(['success' => true, 'id' => $id, 'status' => $status, 'message' => 'Enrollment #' . $id . ' → ' . $status, 'counts' => $counts]);

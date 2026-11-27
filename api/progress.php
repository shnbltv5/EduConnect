<?php
/* =========================================================
   POST api/progress.php   body (JSON): {"lesson_id": 2, "completed": true}
   Marks a lesson as completed / not completed (AJAX).
   Only a student with access to the course can do it.
   Answer: {"success": true, "completed": true, "progress": {"done":2,"total":3,"percent":67}}
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}
$user = api_require_login();
if (!csrf_is_valid()) {
    json_error('Invalid CSRF token. Reload the page.', 403);
}
$input = json_decode(file_get_contents('php://input'), true);
$lessonId = (int)($input['lesson_id'] ?? 0);
$completed = !empty($input['completed']);

$lesson = db_one('SELECT id, course_id FROM lessons WHERE id = ?', [$lessonId]);
if (!$lesson) {
    json_error('Lesson not found.', 404);
}
if ($user['role'] !== 'student' || !can_access_course($user, (int)$lesson['course_id'])) {
    json_error('You do not have access to this course.', 403);
}

if ($completed) {
    try {
        db_exec('INSERT INTO lesson_progress (user_id, lesson_id) VALUES (?, ?)', [$user['id'], $lessonId]);
    } catch (PDOException $e) {
        if (!is_constraint_error($e)) {      // 23000 = already marked: nothing to do
            throw $e;
        }
    }
} else {
    db_exec('DELETE FROM lesson_progress WHERE user_id = ? AND lesson_id = ?', [$user['id'], $lessonId]);
}

json_response([
    'success'   => true,
    'completed' => $completed,
    'progress'  => course_progress((int)$user['id'], (int)$lesson['course_id']),
]);

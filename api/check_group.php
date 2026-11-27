<?php
/* =========================================================
   GET api/check_group.php?group_id=5
   AJAX check of a group BEFORE the enrollment is sent:
   free seats, finished group, already enrolled, and
   SCHEDULE CONFLICTS with the student's other courses.
   Answer:
   { "success": true, "ok": false, "seats_left": 3, "capacity": 20,
     "conflicts": [{"course":"Introduction to Python","day":"Mon","time":"14:00–15:30"}],
     "message": "Schedule conflict: ..." }
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}
$user = api_require_login();
$groupId = int_param($_GET, 'group_id');
$group = $groupId ? get_group($groupId) : false;
if (!$group || !$group['is_published']) {
    json_error('Group not found.', 404);
}

$seatsLeft = max(0, (int)$group['capacity'] - seats_taken($groupId));
$conflicts = find_schedule_conflicts((int)$user['id'], $groupId);
$existing = active_enrollment_in_course((int)$user['id'], (int)$group['course_id']);

$ok = true;
$message = '✓ The time is free in your schedule. ' . $seatsLeft . ' seats left.';
if ($group['end_date'] < date('Y-m-d')) {
    $ok = false;
    $message = 'This group has already finished.';
} elseif ($existing) {
    $ok = false;
    $message = 'You are already enrolled in this course (#' . $existing['id'] . ').';
} elseif ($conflicts) {
    $ok = false;
    $message = conflict_message($conflicts) . ' Please choose another group.';
} elseif ($seatsLeft === 0) {
    $ok = false;
    $message = 'This group is full. Please choose another group.';
}

json_response([
    'success'    => true,
    'ok'         => $ok,
    'message'    => $message,
    'seats_left' => $seatsLeft,
    'capacity'   => (int)$group['capacity'],
    'conflicts'  => array_map(fn($c) => [
        'course' => $c['course_title'],
        'day'    => weekday_short((int)$c['weekday']),
        'time'   => format_time($c['start_time']) . '–' . format_time($c['end_time']),
    ], $conflicts),
]);

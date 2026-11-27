<?php
/* =========================================================
   enrollment.php — BUSINESS LOGIC of the platform:
   1) a student cannot have two courses at the same time
      (schedule conflict, e.g. two groups on Monday at 14:00);
   2) a group cannot have more students than its capacity;
   3) a student cannot enroll in the same course twice;
   4) lessons are open only after the enrollment is confirmed.
   The enrollment itself is saved in a TRANSACTION.
   ========================================================= */

class EnrollmentException extends Exception
{
}

const ACTIVE_STATUSES = "('Pending', 'Confirmed')";

function get_group(int $groupId)
{
    return db_one('SELECT g.*, c.title AS course_title, c.price, c.is_published
                   FROM course_groups g JOIN courses c ON c.id = g.course_id
                   WHERE g.id = ?', [$groupId]);
}

function seats_taken(int $groupId): int
{
    return (int)db_value('SELECT COUNT(*) FROM enrollments WHERE group_id = ? AND status IN ' . ACTIVE_STATUSES, [$groupId]);
}

// Lessons of the chosen group that overlap with lessons of the student's other active groups.
// Two lessons overlap if they are on the same weekday, their times cross
// (start1 < end2 AND start2 < end1) and the groups run in the same period of dates.
function find_schedule_conflicts(int $userId, int $groupId): array
{
    return db_all("
        SELECT c.title AS course_title, g2.name AS group_name, e.id AS enrollment_id,
               s2.weekday, s2.start_time, s2.end_time
        FROM course_groups g1
        JOIN group_sessions s1 ON s1.group_id = g1.id
        JOIN enrollments e     ON e.user_id = :user AND e.status IN " . ACTIVE_STATUSES . "
        JOIN course_groups g2  ON g2.id = e.group_id
        JOIN group_sessions s2 ON s2.group_id = g2.id
        JOIN courses c         ON c.id = g2.course_id
        WHERE g1.id = :group
          AND g2.id <> g1.id
          AND s1.weekday = s2.weekday
          AND s1.start_time < s2.end_time AND s2.start_time < s1.end_time
          AND g1.start_date <= g2.end_date AND g2.start_date <= g1.end_date
        ORDER BY s2.weekday, s2.start_time", [':user' => $userId, ':group' => $groupId]);
}

function conflict_message(array $conflicts): string
{
    $parts = [];
    foreach ($conflicts as $c) {
        $parts[] = '"' . $c['course_title'] . '" (' . weekday_short((int)$c['weekday']) . ' '
            . format_time($c['start_time']) . '–' . format_time($c['end_time']) . ')';
    }
    return 'Schedule conflict: at this time you already study ' . implode(', ', array_unique($parts)) . '.';
}

// Active enrollment of the user in any group of the course (or false)
function active_enrollment_in_course(int $userId, int $courseId)
{
    return db_one('SELECT e.* FROM enrollments e JOIN course_groups g ON g.id = e.group_id
                   WHERE e.user_id = ? AND g.course_id = ? AND e.status IN ' . ACTIVE_STATUSES . '
                   ORDER BY e.id DESC LIMIT 1', [$userId, $courseId]);
}

/**
 * Enrolls the user in a group. Everything happens in ONE transaction:
 * the group row is locked (SELECT ... FOR UPDATE), so two students cannot take
 * the last seat at the same moment. Any failed check -> rollBack().
 * Returns the id of the new enrollment.
 */
function enroll_user(int $userId, int $groupId, string $phone, string $comment): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT g.id, g.course_id, g.capacity, g.end_date, c.price, c.is_published
                               FROM course_groups g JOIN courses c ON c.id = g.course_id
                               WHERE g.id = ? FOR UPDATE');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        if (!$group || !$group['is_published']) {
            throw new EnrollmentException('This group does not exist.');
        }
        if ($group['end_date'] < date('Y-m-d')) {
            throw new EnrollmentException('This group has already finished. Please choose another group.');
        }
        $existing = active_enrollment_in_course($userId, (int)$group['course_id']);
        if ($existing) {
            throw new EnrollmentException('You are already enrolled in this course (#' . $existing['id'] . ').');
        }
        $conflicts = find_schedule_conflicts($userId, $groupId);
        if ($conflicts) {
            throw new EnrollmentException(conflict_message($conflicts));
        }
        if (seats_taken($groupId) >= (int)$group['capacity']) {
            throw new EnrollmentException('Sorry, this group is full. Please choose another group.');
        }

        $insert = $pdo->prepare('INSERT INTO enrollments (user_id, group_id, status, price_paid, phone, comment)
                                 VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$userId, $groupId, 'Pending', (int)$group['price'], normalize_phone($phone), trim($comment) === '' ? null : trim($comment)]);
        $id = (int)$pdo->lastInsertId();

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();                    // nothing is saved if anything went wrong
        throw $e;
    }
}

// Can the user watch the lessons of the course? (confirmed / completed enrollment or admin)
function can_access_course(?array $user, int $courseId): bool
{
    if ($user === null) {
        return false;
    }
    if ($user['role'] === 'admin') {
        return true;
    }
    return (bool)db_value("SELECT COUNT(*) FROM enrollments e JOIN course_groups g ON g.id = e.group_id
                           WHERE e.user_id = ? AND g.course_id = ? AND e.status IN ('Confirmed', 'Completed')",
                           [$user['id'], $courseId]);
}

// Lessons completed by the user in a course: [lesson_id => true]
function completed_lessons(int $userId, int $courseId): array
{
    $rows = db_all('SELECT p.lesson_id FROM lesson_progress p JOIN lessons l ON l.id = p.lesson_id
                    WHERE p.user_id = ? AND l.course_id = ?', [$userId, $courseId]);
    return array_fill_keys(array_map('intval', array_column($rows, 'lesson_id')), true);
}

function course_progress(int $userId, int $courseId): array
{
    $total = (int)db_value('SELECT COUNT(*) FROM lessons WHERE course_id = ?', [$courseId]);
    $done = count(completed_lessons($userId, $courseId));
    return ['done' => $done, 'total' => $total, 'percent' => $total ? (int)round($done * 100 / $total) : 0];
}

// The nearest class of the user (for the home page): date, time and course
function next_class(int $userId): ?array
{
    $rows = db_all("SELECT c.title, c.id AS course_id, g.start_date, g.end_date, s.weekday, s.start_time, s.end_time
                    FROM enrollments e
                    JOIN course_groups g ON g.id = e.group_id
                    JOIN courses c ON c.id = g.course_id
                    JOIN group_sessions s ON s.group_id = g.id
                    WHERE e.user_id = ? AND e.status IN ('Pending', 'Confirmed') AND g.end_date >= ?",
                    [$userId, date('Y-m-d')]);
    $best = null;
    $now = time();
    foreach ($rows as $r) {
        // first day >= today (and >= start of the group) with the right weekday
        $from = max(strtotime(date('Y-m-d')), strtotime($r['start_date']));
        for ($i = 0; $i < 14; $i++) {
            $day = strtotime("+$i day", $from);
            $moment = strtotime(date('Y-m-d', $day) . ' ' . $r['start_time']);
            if ((int)date('N', $day) === (int)$r['weekday'] && $moment > $now && date('Y-m-d', $day) <= $r['end_date']) {
                if ($best === null || $moment < $best['timestamp']) {
                    $best = $r + ['timestamp' => $moment];
                }
                break;
            }
        }
    }
    return $best;
}

// Enrollments of one user with course, group, schedule and progress (profile page)
function user_enrollments(int $userId): array
{
    $rows = db_all("
        SELECT e.id, e.status, e.created_at, e.price_paid, g.id AS group_id, g.name AS group_name, g.format,
               g.start_date, g.end_date, g.location, c.id AS course_id, c.title, c.image,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lessons_total,
               (SELECT COUNT(*) FROM lesson_progress p JOIN lessons l2 ON l2.id = p.lesson_id
                 WHERE p.user_id = e.user_id AND l2.course_id = c.id) AS lessons_done
        FROM enrollments e
        JOIN course_groups g ON g.id = e.group_id
        JOIN courses c ON c.id = g.course_id
        WHERE e.user_id = ?
        ORDER BY e.created_at DESC, e.id DESC", [$userId]);
    foreach ($rows as &$r) {
        $r['sessions'] = group_sessions((int)$r['group_id']);
    }
    return $rows;
}

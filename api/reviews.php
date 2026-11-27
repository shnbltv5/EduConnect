<?php
/* =========================================================
   POST api/reviews.php   body (JSON): {"course_id": 3, "rating": 5, "comment": "..."}
   Adds a review with AJAX. Rules checked on the SERVER:
   - user is logged in, CSRF token is valid
   - rating 1..5, comment 5..500 characters
   - only a student with a Confirmed/Completed enrollment can review
   - one review per course (UNIQUE key in the database)
   Answer: the new review + the new average rating of the course.
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
if (!is_array($input)) {
    json_error('Invalid JSON body.', 400);
}
$courseId = (int)($input['course_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
$comment = trim((string)($input['comment'] ?? ''));

$errors = [];
if ($rating < 1 || $rating > 5) {
    $errors['rating'] = 'Choose a rating from 1 to 5 stars';
}
if (mb_strlen($comment) < 5 || mb_strlen($comment) > 500) {
    $errors['comment'] = 'Comment must be from 5 to 500 characters';
}
if ($errors) {
    json_error('Please correct the form.', 422, ['errors' => $errors]);
}
if (!get_course($courseId)) {
    json_error('Course not found.', 404);
}
if ($user['role'] !== 'student' || !can_access_course($user, $courseId)) {
    json_error('Only students with a confirmed enrollment can review this course.', 403);
}

try {
    db_exec('INSERT INTO reviews (user_id, course_id, rating, comment) VALUES (?, ?, ?, ?)',
            [$user['id'], $courseId, $rating, $comment]);
} catch (PDOException $e) {
    if (is_constraint_error($e)) {
        json_error('You have already reviewed this course.', 409);
    }
    throw $e;
}

$summary = db_one('SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS cnt FROM reviews WHERE course_id = ?', [$courseId]);
json_response([
    'success' => true,
    'message' => 'Thank you! Your review was published.',
    'review'  => [
        'author'  => $user['first_name'] . ' ' . mb_substr($user['last_name'], 0, 1) . '.',
        'rating'  => $rating,
        'comment' => $comment,
        'date'    => date('d.m.Y'),
    ],
    'average' => (float)$summary['avg_rating'],
    'count'   => (int)$summary['cnt'],
], 201);

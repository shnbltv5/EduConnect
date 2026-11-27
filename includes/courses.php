<?php
/* =========================================================
   courses.php — reading courses from the database:
   catalog search (filters + sorting + pagination),
   one course with its groups, lessons and reviews.
   Used by the PHP pages AND by the JSON API (api/courses.php).
   ========================================================= */

// Course + category + teacher + rating (subquery) + number of students (subquery)
const COURSE_SELECT = "
    SELECT c.id, c.title, c.level, c.price, c.duration_weeks, c.image, c.intro_video,
           c.short_description, c.description, c.is_published, c.created_at,
           c.category_id, c.teacher_id,
           cat.name AS category, t.full_name AS teacher, t.bio AS teacher_bio,
           COALESCE(r.avg_rating, 0) AS rating, COALESCE(r.reviews_count, 0) AS reviews_count,
           COALESCE(s.students, 0) AS students
    FROM courses c
    JOIN categories cat ON cat.id = c.category_id
    JOIN teachers t ON t.id = c.teacher_id
    LEFT JOIN (SELECT course_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS reviews_count
               FROM reviews GROUP BY course_id) r ON r.course_id = c.id
    LEFT JOIN (SELECT g.course_id, COUNT(e.id) AS students
               FROM course_groups g
               JOIN enrollments e ON e.group_id = g.id AND e.status <> 'Cancelled'
               GROUP BY g.course_id) s ON s.course_id = c.id";

const SORT_OPTIONS = [
    'popular'    => 'students DESC, rating DESC',
    'rating'     => 'rating DESC, reviews_count DESC',
    'price_asc'  => 'c.price ASC, c.title ASC',
    'price_desc' => 'c.price DESC, c.title ASC',
    'newest'     => 'c.created_at DESC',
    'title'      => 'c.title ASC',
];

// Cleans the filter values that come from the URL (?q=...&category=...)
function catalog_filters(array $src): array
{
    $q = trim((string)($src['q'] ?? ''));
    $level = (string)($src['level'] ?? '');
    $price = (string)($src['price'] ?? '');
    $rating = (string)($src['rating'] ?? '');
    $sort = (string)($src['sort'] ?? '');
    return [
        'q'        => mb_substr($q, 0, 60),
        'category' => int_param($src, 'category'),
        'level'    => in_array($level, ['Beginner', 'Intermediate', 'Advanced'], true) ? $level : '',
        'price'    => in_array($price, ['free', 'under30', 'over30'], true) ? $price : '',
        'rating'   => in_array($rating, ['4', '4.5'], true) ? $rating : '',
        'sort'     => array_key_exists($sort, SORT_OPTIONS) ? $sort : 'popular',
        'page'     => max(1, int_param($src, 'page')),
    ];
}

// Filtering with several conditions + sorting + LIMIT/OFFSET pagination
function search_courses(array $f, int $perPage = PAGE_SIZE): array
{
    $where = ['c.is_published = 1'];
    $params = [];
    if ($f['q'] !== '') {
        $where[] = '(c.title LIKE :q1 OR t.full_name LIKE :q2 OR c.short_description LIKE :q3)';
        $like = '%' . $f['q'] . '%';
        $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
    }
    if ($f['category'] > 0) {
        $where[] = 'c.category_id = :category';
        $params[':category'] = $f['category'];
    }
    if ($f['level'] !== '') {
        $where[] = 'c.level = :level';
        $params[':level'] = $f['level'];
    }
    if ($f['price'] === 'free') {
        $where[] = 'c.price = 0';
    } elseif ($f['price'] === 'under30') {
        $where[] = 'c.price > 0 AND c.price < 30000';
    } elseif ($f['price'] === 'over30') {
        $where[] = 'c.price >= 30000';
    }
    if ($f['rating'] !== '') {
        $where[] = 'COALESCE(r.avg_rating, 0) >= :rating';
        $params[':rating'] = (float)$f['rating'];
    }
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    // 1) how many courses match (for the pagination)
    $total = (int)db_value('SELECT COUNT(*) FROM (' . COURSE_SELECT . $whereSql . ') AS found', $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($f['page'], $pages);

    // 2) only the courses of the current page
    $sql = COURSE_SELECT . $whereSql . ' ORDER BY ' . SORT_OPTIONS[$f['sort']] . ', c.id LIMIT :limit OFFSET :offset';
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function get_categories(): array
{
    return db_all('SELECT id, name FROM categories ORDER BY name');
}

function get_course(int $id, bool $onlyPublished = true)
{
    return db_one(COURSE_SELECT . ' WHERE c.id = ?' . ($onlyPublished ? ' AND c.is_published = 1' : ''), [$id]);
}

function course_features(int $courseId): array
{
    return db_all('SELECT feature FROM course_features WHERE course_id = ? ORDER BY position, id', [$courseId]);
}

// Top courses for the home page
function popular_courses(int $limit = 3): array
{
    $stmt = db()->prepare(COURSE_SELECT . ' WHERE c.is_published = 1 ORDER BY students DESC, rating DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Groups of a course with their lesson times and the number of taken seats
function course_groups(int $courseId): array
{
    $groups = db_all("
        SELECT g.*, COUNT(e.id) AS taken
        FROM course_groups g
        LEFT JOIN enrollments e ON e.group_id = g.id AND e.status IN ('Pending', 'Confirmed')
        WHERE g.course_id = ?
        GROUP BY g.id, g.course_id, g.name, g.format, g.start_date, g.end_date, g.capacity, g.location
        ORDER BY g.start_date, g.name", [$courseId]);
    foreach ($groups as &$g) {
        $g['sessions'] = group_sessions((int)$g['id']);
        $g['seats_left'] = max(0, (int)$g['capacity'] - (int)$g['taken']);
        $g['is_over'] = $g['end_date'] < date('Y-m-d');
    }
    return $groups;
}

function group_sessions(int $groupId): array
{
    return db_all('SELECT weekday, start_time, end_time FROM group_sessions WHERE group_id = ? ORDER BY weekday, start_time', [$groupId]);
}

function course_lessons(int $courseId): array
{
    return db_all('SELECT * FROM lessons WHERE course_id = ? ORDER BY position, id', [$courseId]);
}

function lesson_materials(int $lessonId): array
{
    return db_all('SELECT * FROM materials WHERE lesson_id = ? ORDER BY id', [$lessonId]);
}

function course_reviews(int $courseId): array
{
    return db_all('SELECT r.id, r.rating, r.comment, r.created_at, u.first_name, u.last_name
                   FROM reviews r JOIN users u ON u.id = r.user_id
                   WHERE r.course_id = ? ORDER BY r.created_at DESC', [$courseId]);
}

// How many reviews have 5, 4, 3, 2, 1 stars (GROUP BY)
function rating_distribution(int $courseId): array
{
    $rows = db_all('SELECT rating, COUNT(*) AS cnt FROM reviews WHERE course_id = ? GROUP BY rating', [$courseId]);
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($rows as $r) {
        $dist[(int)$r['rating']] = (int)$r['cnt'];
    }
    return $dist;
}

// HTML of one course card (the same card is built by js/main.js for AJAX results)
function course_card_html(array $c): string
{
    $free = (int)$c['price'] === 0;
    return '<article class="card">'
        . '<div class="card__image-wrap"><img class="card__image" src="' . h(url('images/courses/' . $c['image'])) . '" alt="' . h($c['title']) . '">'
        . '<span class="card__badge">' . h($c['category']) . '</span></div>'
        . '<div class="card__body">'
        . '<h3 class="card__title">' . h($c['title']) . '</h3>'
        . '<p class="card__meta">' . h($c['teacher']) . ' · ' . h($c['level']) . ' · ' . (int)$c['duration_weeks'] . ' weeks</p>'
        . '<p class="card__text">' . h($c['short_description']) . '</p>'
        . '<div class="card__footer"><span class="' . ($free ? 'price price--free' : 'price') . '">' . format_price($c['price']) . '</span>'
        . '<span class="stars" title="Rating ' . h($c['rating']) . '">' . render_stars($c['rating']) . ' ' . h($c['rating'] > 0 ? $c['rating'] : 'new') . '</span></div>'
        . '<p class="card__students">👥 ' . (int)$c['students'] . ' students · ' . (int)$c['reviews_count'] . ' reviews</p>'
        . '<a href="' . h(url('pages/details.php?id=' . (int)$c['id'])) . '" class="btn btn--outline btn--full">View details</a>'
        . '</div></article>';
}

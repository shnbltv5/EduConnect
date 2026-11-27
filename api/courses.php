<?php
/* =========================================================
   GET api/courses.php?q=&category=&level=&price=&rating=&sort=&page=
   Web service for the catalog (AJAX search, filters, sorting,
   pagination). Returns structured JSON:
   {
     "success": true,
     "filters": {...},              cleaned filter values
     "pagination": {"page":1,"pages":2,"total":9,"per_page":6},
     "courses": [ {id, title, category, teacher, level, price, rating, students, ...}, ... ]
   }
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

$filters = catalog_filters($_GET);
$result = search_courses($filters);

$courses = array_map(function ($c) {
    return [
        'id'                => (int)$c['id'],
        'title'             => $c['title'],
        'category'          => $c['category'],
        'teacher'           => $c['teacher'],
        'level'             => $c['level'],
        'price'             => (int)$c['price'],
        'duration_weeks'    => (int)$c['duration_weeks'],
        'image'             => $c['image'],
        'short_description' => $c['short_description'],
        'rating'            => (float)$c['rating'],
        'reviews_count'     => (int)$c['reviews_count'],
        'students'          => (int)$c['students'],
    ];
}, $result['items']);

json_response([
    'success'    => true,
    'filters'    => $filters,
    'pagination' => ['page' => $result['page'], 'pages' => $result['pages'], 'total' => $result['total'], 'per_page' => PAGE_SIZE],
    'courses'    => $courses,
]);

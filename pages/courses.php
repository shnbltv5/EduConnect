<?php
/* =========================================================
   pages/courses.php — course catalog.
   The first page is rendered by PHP (works even without JS).
   After that js/courses.js loads results with AJAX from
   api/courses.php: search, filters, sorting and pagination
   change WITHOUT reloading the page.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';

$filters = catalog_filters($_GET);
$result = search_courses($filters);
$categories = get_categories();

$pageTitle = 'Courses';
$activePage = 'courses';
$pageScripts = ['courses.js'];
include __DIR__ . '/../includes/header.php';

// <option> helper: keeps the selected value after reload
$opt = function (string $value, string $label, string $current): string {
    return '<option value="' . h($value) . '"' . ($value === $current ? ' selected' : '') . '>' . h($label) . '</option>';
};
?>
<section class="page-header">
  <div class="container">
    <h1>Course catalog</h1>
    <p>Find the course that fits you: search, filter by category, level, price and rating, and sort the results</p>
  </div>
</section>

<div class="container">
  <!-- Search & filters (GET form: without JavaScript it simply reloads the page) -->
  <form class="filters filters--catalog" id="filterForm" role="search" method="get" action="courses.php">
    <div class="filters__search">
      <label for="searchInput">🔍 Search</label>
      <input type="search" id="searchInput" name="q" value="<?= h($filters['q']) ?>" placeholder="Course name, teacher..." maxlength="60">
    </div>
    <div>
      <label for="categorySelect">Category</label>
      <select id="categorySelect" name="category">
        <option value="">All</option>
        <?php foreach ($categories as $cat): ?>
          <?= $opt((string)$cat['id'], $cat['name'], (string)$filters['category']) ?>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="levelSelect">Level</label>
      <select id="levelSelect" name="level">
        <?= $opt('', 'All', $filters['level']) ?>
        <?= $opt('Beginner', 'Beginner', $filters['level']) ?>
        <?= $opt('Intermediate', 'Intermediate', $filters['level']) ?>
        <?= $opt('Advanced', 'Advanced', $filters['level']) ?>
      </select>
    </div>
    <div>
      <label for="priceSelect">Price</label>
      <select id="priceSelect" name="price">
        <?= $opt('', 'Any price', $filters['price']) ?>
        <?= $opt('free', 'Free', $filters['price']) ?>
        <?= $opt('under30', 'Under 30 000 ₸', $filters['price']) ?>
        <?= $opt('over30', '30 000 ₸ and more', $filters['price']) ?>
      </select>
    </div>
    <div>
      <label for="ratingSelect">Rating</label>
      <select id="ratingSelect" name="rating">
        <?= $opt('', 'Any rating', $filters['rating']) ?>
        <?= $opt('4.5', '★ 4.5 and up', $filters['rating']) ?>
        <?= $opt('4', '★ 4.0 and up', $filters['rating']) ?>
      </select>
    </div>
    <div>
      <label for="sortSelect">Sort by</label>
      <select id="sortSelect" name="sort">
        <?= $opt('popular', 'Most popular', $filters['sort']) ?>
        <?= $opt('rating', 'Best rating', $filters['sort']) ?>
        <?= $opt('price_asc', 'Price: low to high', $filters['sort']) ?>
        <?= $opt('price_desc', 'Price: high to low', $filters['sort']) ?>
        <?= $opt('newest', 'Newest', $filters['sort']) ?>
        <?= $opt('title', 'Title A–Z', $filters['sort']) ?>
      </select>
    </div>
    <div class="filters__buttons">
      <button type="submit" class="btn">Search</button>
      <a href="courses.php" class="btn btn--outline" id="resetFilters" title="Reset filters" aria-label="Reset filters">↺</a>
    </div>
  </form>

  <p class="results-info" id="resultsInfo" aria-live="polite">
    Showing <?= count($result['items']) ?> of <?= $result['total'] ?> courses · page <?= $result['page'] ?> of <?= $result['pages'] ?></p>

  <!-- Course cards (PHP renders the first page, then courses.js replaces them) -->
  <section class="cards" id="coursesGrid" aria-label="Courses">
    <?php if (!$result['items']): ?>
      <div class="empty-state empty-state--wide">
        <p class="empty-state__icon">🔍</p>
        <h3>No courses found</h3>
        <p>Try another search word or reset the filters.</p>
      </div>
    <?php endif; ?>
    <?php foreach ($result['items'] as $course): ?>
      <?= course_card_html($course) ?>
    <?php endforeach; ?>
  </section>

  <!-- Pagination -->
  <nav class="pagination" id="pagination" aria-label="Pages">
    <?php if ($result['pages'] > 1): ?>
      <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
        <a class="pagination__link<?= $p === $result['page'] ? ' active' : '' ?>" data-page="<?= $p ?>"
           href="?<?= h(http_build_query(array_merge($filters, ['page' => $p]))) ?>"><?= $p ?></a>
      <?php endfor; ?>
    <?php endif; ?>
  </nav>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

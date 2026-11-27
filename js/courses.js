/* =========================================================
   courses.js — AJAX catalog.
   Every change of the search / filters / sorting / page sends
   a GET request to api/courses.php and redraws ONLY the cards,
   the counter and the pagination (the page is not reloaded).
   ========================================================= */

const filterForm = document.getElementById("filterForm");
const searchInput = document.getElementById("searchInput");
const coursesGrid = document.getElementById("coursesGrid");
const resultsInfo = document.getElementById("resultsInfo");
const pagination = document.getElementById("pagination");
let currentPage = 1;
let searchTimer = null;
let lastRequest = 0;                       // to ignore answers of old (slow) requests

// Form values -> "q=python&level=Beginner&page=1"
function buildQuery(page) {
  const params = new URLSearchParams(new FormData(filterForm));
  params.set("page", page);
  // remove empty values to keep the URL short
  Array.from(params.keys()).forEach(function (key) {
    if (params.get(key) === "") {
      params.delete(key);
    }
  });
  return params.toString();
}

async function loadCourses(page) {
  currentPage = page || 1;
  const query = buildQuery(currentPage);
  const requestId = ++lastRequest;

  // 1) loading indicator
  coursesGrid.setAttribute("aria-busy", "true");
  coursesGrid.classList.add("is-loading");
  resultsInfo.innerHTML = spinnerHtml("Loading courses...");

  try {
    // 2) asynchronous request to the PHP web service
    const data = await apiRequest("api/courses.php?" + query);
    if (requestId !== lastRequest) {
      return;                              // a newer request was sent meanwhile
    }
    // 3) success: draw the cards
    renderCourses(data);
    // the URL changes too, so the result can be shared or reloaded
    history.replaceState(null, "", "?" + query);
  } catch (error) {
    if (requestId !== lastRequest) {
      return;
    }
    // 4) error: show a message and a "Try again" button
    coursesGrid.innerHTML = `
      <div class="empty-state empty-state--wide empty-state--error">
        <p class="empty-state__icon">⚠️</p>
        <h3>Could not load courses</h3>
        <p>${escapeHtml(error.message)}</p>
        <p><button type="button" class="btn" id="retryButton">Try again</button></p>
      </div>`;
    resultsInfo.textContent = "";
    pagination.innerHTML = "";
    document.getElementById("retryButton").addEventListener("click", function () {
      loadCourses(currentPage);
    });
  } finally {
    coursesGrid.removeAttribute("aria-busy");
    coursesGrid.classList.remove("is-loading");
  }
}

function renderCourses(data) {
  const info = data.pagination;
  coursesGrid.innerHTML = "";

  // empty result
  if (data.courses.length === 0) {
    coursesGrid.innerHTML = `
      <div class="empty-state empty-state--wide">
        <p class="empty-state__icon">🔍</p>
        <h3>No courses found</h3>
        <p>Try another search word or reset the filters.</p>
      </div>`;
  } else {
    data.courses.forEach(function (course) {
      coursesGrid.appendChild(createCourseCard(course));
    });
  }

  resultsInfo.textContent = "Showing " + data.courses.length + " of " + info.total + " courses · page " + info.page + " of " + info.pages;
  renderPagination(info);
}

function renderPagination(info) {
  pagination.innerHTML = "";
  if (info.pages <= 1) {
    return;
  }
  for (let p = 1; p <= info.pages; p++) {
    const link = document.createElement("a");
    link.href = "?" + buildQuery(p);
    link.textContent = p;
    link.dataset.page = p;
    link.className = "pagination__link" + (p === info.page ? " active" : "");
    if (p === info.page) {
      link.setAttribute("aria-current", "page");
    }
    pagination.appendChild(link);
  }
}

// ---------- Events ----------
filterForm.addEventListener("submit", function (event) {
  event.preventDefault();                  // no page reload: AJAX instead
  loadCourses(1);
});

// live search while typing (waits 300 ms after the last key)
searchInput.addEventListener("input", function () {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(function () {
    loadCourses(1);
  }, 300);
});

// every <select> reloads the results immediately
filterForm.querySelectorAll("select").forEach(function (select) {
  select.addEventListener("change", function () {
    loadCourses(1);
  });
});

document.getElementById("resetFilters").addEventListener("click", function (event) {
  event.preventDefault();
  filterForm.reset();
  filterForm.querySelectorAll("select").forEach(function (s) { s.selectedIndex = 0; });
  searchInput.value = "";
  loadCourses(1);
});

// pagination: one listener for all page links (event delegation)
pagination.addEventListener("click", function (event) {
  const link = event.target.closest("a[data-page]");
  if (!link) {
    return;
  }
  event.preventDefault();
  loadCourses(Number(link.dataset.page));
  coursesGrid.scrollIntoView({ behavior: "smooth", block: "start" });
});

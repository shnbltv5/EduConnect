/* =========================================================
   main.js — code shared by all pages:
   burger menu, confirmation dialogs, flash messages, toast,
   apiRequest() — one wrapper for all AJAX (fetch) requests,
   and the course card used by the catalog.
   ========================================================= */

// Path to the project root ("" or "../") and the CSRF token are printed by header.php
const BASE_URL = document.querySelector('meta[name="base-url"]').content;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

// ---------- AJAX: one function for every request to our PHP API ----------
// Sends JSON or GET parameters, adds the CSRF token, parses the JSON answer
// and turns HTTP errors (401, 403, 409, 500...) into a normal JS error.
async function apiRequest(path, options) {
  options = options || {};
  const settings = {
    method: options.method || "GET",
    headers: { "Accept": "application/json", "X-CSRF-Token": CSRF_TOKEN },
    credentials: "same-origin"
  };
  if (options.body) {
    settings.headers["Content-Type"] = "application/json";
    settings.body = JSON.stringify(options.body);
  }

  let response;
  try {
    response = await fetch(BASE_URL + path, settings);
  } catch (networkError) {
    throw new Error("Network error: the server is not reachable.");
  }

  let data;
  try {
    data = await response.json();
  } catch (parseError) {
    throw new Error("The server returned an invalid answer (HTTP " + response.status + ").");
  }
  if (!response.ok || data.success === false) {
    const error = new Error(data.error || "Request failed (HTTP " + response.status + ").");
    error.status = response.status;
    error.data = data;
    throw error;
  }
  return data;
}

// ---------- Helpers ----------
function escapeHtml(text) {
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatPrice(price) {
  if (Number(price) === 0) {
    return "Free";
  }
  return Number(price).toLocaleString("ru-RU").replace(/,/g, " ") + " ₸";
}

function renderStars(rating) {
  const full = Math.round(rating);
  let stars = "";
  for (let i = 1; i <= 5; i++) {
    stars += i <= full ? "★" : "☆";
  }
  return stars;
}

// Loading indicator inside any element
function spinnerHtml(text) {
  return '<div class="loading" role="status"><span class="spinner"></span>' + escapeHtml(text || "Loading...") + "</div>";
}

// ---------- Small pop-up message in the corner ----------
function showToast(message, type) {
  let toast = document.getElementById("toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.id = "toast";
    toast.setAttribute("role", "status");
    document.body.appendChild(toast);
  }
  toast.className = "toast toast--" + (type || "info");
  toast.textContent = message;
  // restart the animation
  void toast.offsetWidth;
  toast.classList.add("show");
  clearTimeout(toast.timer);
  toast.timer = setTimeout(function () {
    toast.classList.remove("show");
  }, 3000);
}

// ---------- Course card (same HTML as course_card_html() in PHP) ----------
function createCourseCard(course) {
  const article = document.createElement("article");
  article.className = "card";
  const title = escapeHtml(course.title);
  const rating = Number(course.rating);
  article.innerHTML = `
    <div class="card__image-wrap">
      <img class="card__image" src="${BASE_URL}images/courses/${escapeHtml(course.image)}" alt="${title}">
      <span class="card__badge">${escapeHtml(course.category)}</span>
    </div>
    <div class="card__body">
      <h3 class="card__title">${title}</h3>
      <p class="card__meta">${escapeHtml(course.teacher)} · ${escapeHtml(course.level)} · ${Number(course.duration_weeks)} weeks</p>
      <p class="card__text">${escapeHtml(course.short_description)}</p>
      <div class="card__footer">
        <span class="${Number(course.price) === 0 ? "price price--free" : "price"}">${formatPrice(course.price)}</span>
        <span class="stars" title="Rating ${rating}">${renderStars(rating)} ${rating > 0 ? rating : "new"}</span>
      </div>
      <p class="card__students">👥 ${Number(course.students)} students · ${Number(course.reviews_count)} reviews</p>
      <a href="${BASE_URL}pages/details.php?id=${Number(course.id)}" class="btn btn--outline btn--full">View details</a>
    </div>`;
  return article;
}

// ---------- Burger menu (mobile) ----------
function setupBurgerMenu() {
  const burger = document.getElementById("burger");
  const nav = document.getElementById("nav");
  if (!burger || !nav) {
    return;
  }
  burger.addEventListener("click", function () {
    nav.classList.toggle("nav--open");
    const isOpen = nav.classList.contains("nav--open");
    burger.textContent = isOpen ? "✕" : "☰";
    burger.setAttribute("aria-expanded", isOpen);
  });
}

// ---------- Confirmation dialogs: any <form data-confirm="..."> ----------
function setupConfirmForms() {
  document.addEventListener("submit", function (event) {
    const form = event.target;
    if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
      event.preventDefault();             // the user pressed "Cancel"
    }
  });
}

// ---------- Flash messages from PHP: close button + auto hide ----------
function setupFlashMessages() {
  document.querySelectorAll(".flash").forEach(function (flash) {
    flash.querySelector(".flash__close").addEventListener("click", function () {
      flash.remove();
    });
    if (!flash.classList.contains("alert--error")) {
      setTimeout(function () {
        flash.classList.add("flash--hide");
      }, 6000);
    }
  });
}

// ---------- Start ----------
document.addEventListener("DOMContentLoaded", function () {
  setupBurgerMenu();
  setupConfirmForms();
  setupFlashMessages();
});

/* =========================================================
   admin.js — admin panel:
   1) change the status of an enrollment with AJAX
      (api/admin_enrollment.php) without reloading the page;
   2) <select data-autosubmit> sends its form at once;
   3) group form: add / remove rows with lesson times;
   4) client-side validation of the course form.
   ========================================================= */

// ---------- 1) Enrollment status (AJAX) ----------
// one listener for the whole table = event delegation
const enrollmentsTable = document.getElementById("enrollmentsTable");

if (enrollmentsTable) {
  enrollmentsTable.addEventListener("change", async function (event) {
    const select = event.target.closest("select[data-status-id]");
    if (!select) {
      return;
    }
    const id = Number(select.dataset.statusId);
    const oldStatus = select.dataset.current;
    const newStatus = select.value;

    if (newStatus === "Cancelled" && !confirm("Cancel enrollment #" + id + "?")) {
      select.value = oldStatus;            // the admin changed his mind
      return;
    }

    select.disabled = true;
    const row = select.closest("tr");
    row.classList.add("is-saving");
    try {
      const data = await apiRequest("api/admin_enrollment.php", {
        method: "POST",
        body: { id: id, status: newStatus }
      });
      select.dataset.current = data.status;
      select.className = "status-select status-select--" + data.status.toLowerCase();
      // update the counters at the top of the page
      document.querySelectorAll("[data-count]").forEach(function (counter) {
        counter.textContent = data.counts[counter.dataset.count] || 0;
      });
      row.classList.add("is-saved");
      setTimeout(function () { row.classList.remove("is-saved"); }, 1200);
      showToast("✓ " + data.message, "success");
    } catch (error) {
      select.value = oldStatus;            // put the old value back
      showToast(error.message, "error");
    } finally {
      select.disabled = false;
      row.classList.remove("is-saving");
    }
  });
}

// ---------- 2) Auto-submit selects ----------
document.querySelectorAll("select[data-autosubmit]").forEach(function (select) {
  select.addEventListener("change", function () {
    select.form.submit();
  });
});

// ---------- 3) Group form: rows with lesson times ----------
const sessionRows = document.getElementById("sessionRows");
const addRowButton = document.getElementById("addSessionRow");

if (sessionRows && addRowButton) {
  addRowButton.addEventListener("click", function () {
    const rows = sessionRows.querySelectorAll(".session-row");
    if (rows.length >= 7) {
      showToast("Maximum 7 lesson times", "error");
      return;
    }
    const copy = rows[0].cloneNode(true);  // copy the first row and clear its values
    copy.querySelectorAll("select, input").forEach(function (field) {
      field.value = "";
    });
    sessionRows.appendChild(copy);
    copy.querySelector("select").focus();
  });

  sessionRows.addEventListener("click", function (event) {
    const button = event.target.closest("[data-remove-row]");
    if (!button) {
      return;
    }
    const rows = sessionRows.querySelectorAll(".session-row");
    if (rows.length === 1) {
      rows[0].querySelectorAll("select, input").forEach(function (field) { field.value = ""; });
    } else {
      button.closest(".session-row").remove();
    }
  });

  // check that every filled row has an end time after the start time
  document.getElementById("groupFormEl").addEventListener("submit", function (event) {
    let filled = 0;
    let wrong = false;
    sessionRows.querySelectorAll(".session-row").forEach(function (row) {
      const day = row.querySelector("select").value;
      const times = row.querySelectorAll("input");
      const start = times[0].value;
      const end = times[1].value;
      row.classList.remove("session-row--error");
      if (day === "" && start === "" && end === "") {
        return;
      }
      filled++;
      if (day === "" || start === "" || end === "" || end <= start) {
        wrong = true;
        row.classList.add("session-row--error");
      }
    });
    if (filled === 0 || wrong) {
      event.preventDefault();
      showToast(filled === 0 ? "Add at least one lesson time" : "Every lesson time needs a day, a start and an end after the start", "error");
    }
  });
}

// ---------- 4) Course form ----------
const courseForm = document.getElementById("courseForm");

if (courseForm) {
  enableLiveClear(courseForm);
  courseForm.addEventListener("submit", function (event) {
    clearAllErrors(courseForm);
    const title = document.getElementById("cTitle");
    const category = document.getElementById("cCategory");
    const teacher = document.getElementById("cTeacher");
    const level = document.getElementById("cLevel");
    const price = document.getElementById("cPrice");
    const duration = document.getElementById("cDuration");
    const shortDescription = document.getElementById("cShort");
    const description = document.getElementById("cDescription");
    const features = document.getElementById("cFeatures");
    let isValid = true;

    if (title.value.trim().length < 3) {
      showError(title, "Title must be at least 3 characters");
      isValid = false;
    }
    if (category.value === "") {
      showError(category, "Choose a category");
      isValid = false;
    }
    if (teacher.value === "") {
      showError(teacher, "Choose a teacher");
      isValid = false;
    }
    if (level.value === "") {
      showError(level, "Choose a level");
      isValid = false;
    }
    if (price.value === "" || Number(price.value) < 0 || !Number.isInteger(Number(price.value))) {
      showError(price, "Price must be a whole number, 0 or more (0 = free)");
      isValid = false;
    }
    if (duration.value === "" || Number(duration.value) < 1 || Number(duration.value) > 52) {
      showError(duration, "Duration: from 1 to 52 weeks");
      isValid = false;
    }
    if (shortDescription.value.trim().length < 10) {
      showError(shortDescription, "Short description: at least 10 characters");
      isValid = false;
    }
    if (description.value.trim().length < 20) {
      showError(description, "Description must be at least 20 characters");
      isValid = false;
    }
    if (features.value.split("\n").filter(function (f) { return f.trim() !== ""; }).length === 0) {
      showError(features, "Add at least one feature (one per line)");
      isValid = false;
    }
    if (!isValid) {
      event.preventDefault();
      focusFirstError(courseForm);
    }
  });
}

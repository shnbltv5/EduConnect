/* =========================================================
   validation.js — CLIENT-SIDE form validation helpers.
   The same rules are checked again in PHP (includes/validation.php),
   because JavaScript can be switched off in the browser.
   ========================================================= */

// ---------- Checks (return true / false) ----------
function isEmpty(value) {
  return value.trim() === "";
}

function isValidEmail(email) {
  // something@something.something
  const pattern = /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/;
  return pattern.test(email.trim());
}

function isValidName(name) {
  // only letters (Latin / Cyrillic / Kazakh), spaces and "-" ; from 2 to 50 symbols
  const pattern = /^[A-Za-zА-Яа-яЁёӘәҒғҚқҢңӨөҰұҮүҺһІі\s-]{2,50}$/;
  return pattern.test(name.trim());
}

function isValidPhone(phone) {
  // Kazakhstan format: +7XXXXXXXXXX or 8XXXXXXXXXX (spaces, "-" and brackets are allowed)
  const digitsOnly = phone.replace(/[\s\-()]/g, "");
  return /^(\+7|8)\d{10}$/.test(digitsOnly);
}

function isStrongPassword(password) {
  // at least 8 characters, letters and digits
  return password.length >= 8 && /[A-Za-z]/.test(password) && /\d/.test(password);
}

function todayString() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  return now.getFullYear() + "-" + month + "-" + day;
}

function isFutureDate(dateString) {
  return dateString > todayString();     // "YYYY-MM-DD" strings can be compared directly
}

// ---------- Showing / hiding error messages ----------
// Each input is inside <div class="form-group"> that also has <p class="error-message">
function showError(input, message) {
  const group = input.closest(".form-group");
  group.classList.add("has-error");
  group.querySelector(".error-message").textContent = "⚠ " + message;
  input.setAttribute("aria-invalid", "true");
}

function clearError(input) {
  const group = input.closest(".form-group");
  if (!group) {
    return;
  }
  group.classList.remove("has-error");
  const box = group.querySelector(".error-message");
  if (box) {
    box.textContent = "";
  }
  input.removeAttribute("aria-invalid");
}

function clearAllErrors(form) {
  form.querySelectorAll(".form-group").forEach(function (group) {
    group.classList.remove("has-error");
    const box = group.querySelector(".error-message");
    if (box) {
      box.textContent = "";
    }
  });
  form.querySelectorAll("[aria-invalid]").forEach(function (field) {
    field.removeAttribute("aria-invalid");
  });
}

// Shows a message box at the top of a form (type: "error" | "success" | "info")
function showFormAlert(alertBox, message, type) {
  alertBox.textContent = message;
  alertBox.className = "alert alert--" + type;
}

function hideFormAlert(alertBox) {
  alertBox.textContent = "";
  alertBox.className = "alert hidden";
}

// Remove the red error as soon as the user starts typing / choosing again
function enableLiveClear(form) {
  form.querySelectorAll("input, select, textarea").forEach(function (field) {
    const eventName = field.type === "radio" || field.type === "checkbox" || field.tagName === "SELECT" ? "change" : "input";
    field.addEventListener(eventName, function () {
      clearError(field);
    });
  });
}

// "Empty form submission": true if all text fields are empty
function isFormEmpty(form) {
  const fields = form.querySelectorAll("input[type=text], input[type=email], input[type=password], input[type=tel], textarea");
  for (let i = 0; i < fields.length; i++) {
    if (!isEmpty(fields[i].value)) {
      return false;
    }
  }
  return true;
}

// Focus the first field with an error (helps keyboard users)
function focusFirstError(form) {
  const field = form.querySelector(".has-error input, .has-error select, .has-error textarea");
  if (field) {
    field.focus();
  }
}

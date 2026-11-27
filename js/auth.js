/* =========================================================
   auth.js — registration and login forms in the browser:
   client-side validation (before the form is sent to PHP)
   and a live AJAX check "is this email free?".
   ========================================================= */

// ---------- Registration ----------
const registerForm = document.getElementById("registerForm");

if (registerForm) {
  enableLiveClear(registerForm);
  const alertBox = document.getElementById("registerAlert");
  const email = document.getElementById("email");
  const emailStatus = document.getElementById("emailStatus");
  let emailTimer = null;
  let emailTaken = false;

  // AJAX: ask the server if the email is already registered (0.5 s after the user stops typing)
  email.addEventListener("input", function () {
    clearTimeout(emailTimer);
    emailStatus.textContent = "";
    emailStatus.className = "field-hint";
    emailTaken = false;
    if (!isValidEmail(email.value)) {
      return;
    }
    emailTimer = setTimeout(async function () {
      emailStatus.textContent = "Checking...";
      try {
        const data = await apiRequest("api/check_email.php?email=" + encodeURIComponent(email.value.trim()));
        emailTaken = !data.available;
        emailStatus.textContent = data.available ? "✓ This email is free" : "✗ This email is already registered";
        emailStatus.className = "field-hint " + (data.available ? "field-hint--ok" : "field-hint--bad");
      } catch (error) {
        emailStatus.textContent = "";      // not critical: PHP checks the email again
      }
    }, 500);
  });

  registerForm.addEventListener("submit", function (event) {
    clearAllErrors(registerForm);
    const firstName = document.getElementById("firstName");
    const lastName = document.getElementById("lastName");
    const phone = document.getElementById("phone");
    const birthDate = document.getElementById("birthDate");
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirmPassword");
    let isValid = true;

    // 0) the whole form is empty
    if (isFormEmpty(registerForm)) {
      showFormAlert(alertBox, "Please fill in the form — all required fields are empty.", "error");
      isValid = false;
    } else {
      hideFormAlert(alertBox);
    }

    if (isEmpty(firstName.value)) {
      showError(firstName, "First name is required");
      isValid = false;
    } else if (!isValidName(firstName.value)) {
      showError(firstName, "Use only letters (2–50)");
      isValid = false;
    }

    if (isEmpty(lastName.value)) {
      showError(lastName, "Last name is required");
      isValid = false;
    } else if (!isValidName(lastName.value)) {
      showError(lastName, "Use only letters (2–50)");
      isValid = false;
    }

    if (isEmpty(email.value)) {
      showError(email, "Email is required");
      isValid = false;
    } else if (!isValidEmail(email.value)) {
      showError(email, "Please enter a valid email, e.g. student@mail.com");
      isValid = false;
    } else if (emailTaken) {
      showError(email, "This email is already registered");
      isValid = false;
    }

    if (!isEmpty(phone.value) && !isValidPhone(phone.value)) {
      showError(phone, "Phone format: +7 701 123 45 67");
      isValid = false;
    }

    if (birthDate.value !== "" && isFutureDate(birthDate.value)) {
      showError(birthDate, "Date of birth cannot be in the future");
      isValid = false;
    }

    if (isEmpty(password.value)) {
      showError(password, "Password is required");
      isValid = false;
    } else if (password.value.length < 8) {
      showError(password, "Password must be at least 8 characters");
      isValid = false;
    } else if (!isStrongPassword(password.value)) {
      showError(password, "Password must contain letters and digits");
      isValid = false;
    }

    if (isEmpty(confirmPassword.value)) {
      showError(confirmPassword, "Please confirm your password");
      isValid = false;
    } else if (confirmPassword.value !== password.value) {
      showError(confirmPassword, "Passwords do not match");
      isValid = false;
    }

    if (!isValid) {
      event.preventDefault();               // stop: the form is NOT sent to the server
      focusFirstError(registerForm);
    }
  });
}

// ---------- Login ----------
const loginForm = document.getElementById("loginForm");

if (loginForm) {
  enableLiveClear(loginForm);
  const alertBox = document.getElementById("loginAlert");

  document.getElementById("forgotLink").addEventListener("click", function (event) {
    event.preventDefault();
    showFormAlert(alertBox, "To restore your password, write to support@educonnect.kz from your registered email.", "info");
  });

  loginForm.addEventListener("submit", function (event) {
    clearAllErrors(loginForm);
    const email = document.getElementById("loginEmail");
    const password = document.getElementById("loginPassword");
    let isValid = true;

    if (isEmpty(email.value)) {
      showError(email, "Please enter your email");
      isValid = false;
    } else if (!isValidEmail(email.value)) {
      showError(email, "Incorrect email format");
      isValid = false;
    }
    if (isEmpty(password.value)) {
      showError(password, "Please enter your password");
      isValid = false;
    }
    if (!isValid) {
      event.preventDefault();
      focusFirstError(loginForm);
    }
  });
}
